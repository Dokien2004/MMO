"""Playwright integration for slider captcha solving.

Orchestrates the full captcha-solving pipeline:
1. Detect captcha modal elements on the page
2. Capture background + puzzle piece images
3. Use CV solver to detect gap offset
4. Simulate human-like drag via motion simulator
5. Verify solve result
"""
from __future__ import annotations

import logging
import random
import time
from pathlib import Path
from typing import Any, Optional

import cv2
import numpy as np

from . import cv_solver
from .cv_solver import DetectionResult, detect_gap_offset, save_debug_image
from . import motion_simulator
from .motion_simulator import DragPath, calculate_step_delays, generate_drag_path

logger = logging.getLogger(__name__)

# CSS selectors for Shopee captcha elements — tried in order
SHOPEE_SELECTORS: dict[str, tuple[str, ...]] = {
    "modal": (
        ".shopee-login-captcha",
        "[class*='captcha']",
        "[class*='Captcha']",
        "#captcha-container",
        "[data-testid='captcha']",
        ".verify-wrap",
        "[class*='slider-verify']",
    ),
    "background": (
        ".captcha-image img",
        "[class*='captcha'] img[class*='bg']",
        "[class*='captcha'] canvas",
        ".slider-verify-bg img",
        "[class*='CaptchaBg']",
        "[class*='captcha-bg']",
        "[class*='captcha'] img",
    ),
    "piece": (
        ".captcha-piece img",
        "[class*='captcha'] img[class*='piece']",
        "[class*='captcha'] img[class*='puzzle']",
        "[class*='captcha'] img[class*='jigsaw']",
        ".slider-verify-piece img",
        "[class*='CaptchaPiece']",
        "[class*='captcha-piece']",
    ),
    "slider": (
        ".captcha-slider .slider-handle",
        "[class*='captcha'] [class*='slider-btn']",
        "[class*='captcha'] [class*='slider-handle']",
        "[class*='captcha'] [class*='SliderIcon']",
        ".slider-verify-slider button",
        "[class*='slider'] [class*='handler']",
        "[class*='captcha'] .drag-btn",
    ),
    "slider_track": (
        ".captcha-slider",
        "[class*='captcha'] [class*='slider-track']",
        "[class*='captcha'] [class*='SliderBar']",
        ".slider-verify-track",
        "[class*='slider-bar']",
    ),
}


def solve_captcha(
    page: Any,
    platform: str = "shopee",
    max_attempts: int = 3,
    debug_dir: Optional[str] = None,
    post_solve_delay: tuple[float, float] = (1.5, 3.0),
) -> bool:
    """Attempt to solve slider captcha on the current page.

    Args:
        page: Playwright Page object.
        platform: Marketplace platform (currently only 'shopee').
        max_attempts: Maximum number of solve attempts.
        debug_dir: Directory to save debug images (optional).
        post_solve_delay: (min, max) seconds to wait after solve attempt.

    Returns:
        True if captcha was solved successfully.
    """
    debug_path = Path(debug_dir) if debug_dir else None

    for attempt in range(1, max_attempts + 1):
        logger.info("Captcha solve attempt %d/%d", attempt, max_attempts)
        try:
            solved = _attempt_solve(page, attempt, debug_path)
            time.sleep(random.uniform(*post_solve_delay))

            if _verify_solved(page):
                logger.info("Captcha solved on attempt %d!", attempt)
                return True

            if solved:
                logger.warning("Drag completed but captcha not verified, retrying...")
        except Exception as exc:
            logger.warning("Attempt %d failed: %s", attempt, exc)

        if attempt < max_attempts:
            time.sleep(random.uniform(1.0, 2.0))

    logger.error("Failed to solve captcha after %d attempts", max_attempts)
    return False


def _attempt_solve(page: Any, attempt: int, debug_path: Optional[Path] = None) -> bool:
    """Single captcha solve attempt."""
    elements = _find_captcha_elements(page)
    if not elements.get("slider") or not elements.get("background"):
        logger.warning("Could not find required captcha elements")
        return False

    bg_image, piece_image = _capture_captcha_images(
        page,
        elements.get("background"),
        elements.get("piece"),
        elements.get("modal"),
    )

    if bg_image is None:
        logger.error("Failed to capture background image")
        return False

    if piece_image is None:
        piece_image = _generate_default_piece(bg_image)
        logger.info("Using generated default piece")

    detection = detect_gap_offset(bg_image, piece_image, debug=debug_path is not None)

    if debug_path:
        debug_path.mkdir(parents=True, exist_ok=True)
        save_debug_image(detection, str(debug_path / f"captcha_debug_{attempt}.png"))

    distance = _calculate_drag_distance(
        page, detection, elements["background"], elements["slider"], bg_image
    )
    logger.info("Calculated drag distance: %dpx", distance)

    _execute_drag(page, elements["slider"], distance)
    return True


def _find_captcha_elements(page: Any) -> dict[str, Any]:
    """Locate captcha UI elements on the page using CSS selectors."""
    found: dict[str, Any] = {}

    for name, selectors in SHOPEE_SELECTORS.items():
        for selector in selectors:
            try:
                locator = page.locator(selector).first
                if locator.is_visible(timeout=800):
                    found[name] = locator
                    break
            except Exception:
                continue

    return found


def _capture_captcha_images(
    page: Any,
    bg_element: Any,
    piece_element: Any,
    modal_element: Any,
) -> tuple[Optional[np.ndarray], Optional[np.ndarray]]:
    """Capture background and puzzle piece images from the page."""
    bg_image = _try_extract_image_from_src(page, bg_element) if bg_element else None
    piece_image = _try_extract_image_from_src(page, piece_element) if piece_element else None

    # Fallback: screenshot the elements directly
    if bg_image is None and bg_element is not None:
        try:
            screenshot = bg_element.screenshot(timeout=3000)
            if screenshot:
                arr = np.frombuffer(screenshot, np.uint8)
                bg_image = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        except Exception as exc:
            logger.warning("Background screenshot failed: %s", exc)

    if piece_image is None and piece_element is not None:
        try:
            screenshot = piece_element.screenshot(timeout=3000)
            if screenshot:
                arr = np.frombuffer(screenshot, np.uint8)
                piece_image = cv2.imdecode(arr, cv2.IMREAD_UNCHANGED)
        except Exception as exc:
            logger.warning("Piece screenshot failed: %s", exc)

    return bg_image, piece_image


def _try_extract_image_from_src(page: Any, element: Any) -> Optional[np.ndarray]:
    """Try to download the image from element's src attribute."""
    try:
        src = element.get_attribute("src")
        if not src:
            return None

        if src.startswith("data:"):
            # Base64 data URI
            import base64

            header, data = src.split(",", 1)
            raw = base64.b64decode(data)
            arr = np.frombuffer(raw, np.uint8)
            flags = cv2.IMREAD_UNCHANGED if "piece" in str(element) else cv2.IMREAD_COLOR
            return cv2.imdecode(arr, flags)

        # Fetch via page context
        response = page.request.get(src)
        if response.ok:
            arr = np.frombuffer(response.body(), np.uint8)
            return cv2.imdecode(arr, cv2.IMREAD_UNCHANGED)
    except Exception as exc:
        logger.debug("Image extraction from src failed: %s", exc)

    return None


def _generate_default_piece(bg_image: np.ndarray) -> np.ndarray:
    """Generate a simple default puzzle piece shape when actual piece is unavailable."""
    h, w = bg_image.shape[:2]
    piece_h = h
    piece_w = max(40, w // 8)
    piece = np.zeros((piece_h, piece_w), dtype=np.uint8)
    # Create a rectangle with rounded corners effect
    cv2.rectangle(piece, (2, 2), (piece_w - 2, piece_h - 2), 255, -1)
    return piece


def _calculate_drag_distance(
    page: Any,
    detection: DetectionResult,
    bg_element: Any,
    slider_element: Any,
    bg_image: np.ndarray,
) -> int:
    """Convert CV-detected gap offset to actual pixel drag distance.

    The gap X-offset is in the image coordinate space.
    We need to scale it to the rendered element size on the page.
    """
    try:
        bg_box = bg_element.bounding_box()
        if bg_box:
            image_w = bg_image.shape[1]
            rendered_w = bg_box.get("width", image_w)
            scale = rendered_w / max(image_w, 1)
            return max(1, int(detection.x_offset * scale))
    except Exception as exc:
        logger.warning("Could not get bounding box for scaling: %s", exc)

    return detection.x_offset


def _execute_drag(page: Any, slider_element: Any, distance: int) -> None:
    """Execute a human-like drag on the slider element."""
    box = slider_element.bounding_box()
    if not box:
        raise RuntimeError("Cannot get slider bounding box")
    if box is None:
        raise RuntimeError("Slider bounding box is None")

    start_x = int(box["x"] + box["width"] / 2)
    start_y = int(box["y"] + box["height"] / 2)
    target_x = start_x + distance
    target_y = start_y + random.randint(-2, 2)

    path = generate_drag_path(start_x, start_y, target_x, target_y)
    delays = calculate_step_delays(path)

    logger.info(
        "Dragging from (%d,%d) to (%d,%d), %d steps, ~%dms",
        start_x, start_y, target_x, target_y, len(path.steps), path.total_duration_ms,
    )

    page.mouse.move(start_x, start_y)
    time.sleep(random.uniform(0.1, 0.3))
    page.mouse.down()

    for step, delay_ms in zip(path.steps, delays):
        page.mouse.move(step.x, step.y)
        total_pause = delay_ms + step.pause_ms
        if total_pause > 0:
            time.sleep(total_pause / 1000.0)

    page.mouse.up()
    logger.info("Drag complete")


def _is_verify_page(page: Any) -> bool:
    """Check if the current page URL indicates a verification/captcha page."""
    url = (getattr(page, "url", "") or "").lower()
    return any(token in url for token in ("verify", "captcha", "punish"))


def _is_traffic_error_page(page: Any) -> bool:
    """Check if the page is a Shopee traffic verification error (type=4 etc).

    These are full-page redirects, NOT slider puzzle overlays.
    The CV solver cannot handle them — they require session rotation or
    manual intervention.
    """
    url = (getattr(page, "url", "") or "").lower()
    return "verify/traffic" in url


def _verify_solved(page: Any) -> bool:
    """Check if the captcha was successfully solved.

    Priority order:
    1. Check URL first — if still on verify/captcha page, NOT solved
    2. Check if captcha modal disappeared
    3. Check for success indicators in page content
    """
    time.sleep(0.5)

    url = (getattr(page, "url", "") or "").lower()
    if "verify/traffic" in url or "punish" in url:
        logger.debug("Still on verify/traffic page — not solved")
        return False

    # Check if captcha modal is still visible
    for selector in SHOPEE_SELECTORS["modal"]:
        try:
            locator = page.locator(selector).first
            if locator.is_visible(timeout=500):
                # Modal still visible — check for success indicators inside
                try:
                    text = locator.inner_text(timeout=500).lower()
                    if any(w in text for w in ("thành công", "success", "hoàn thành")):
                        logger.debug("Success indicator found in captcha modal")
                        return True
                except Exception:
                    pass
                logger.debug("Captcha modal still visible — not solved")
                return False
        except Exception:
            continue

    # No captcha modal found
    if "captcha" not in url and "verify" not in url:
        logger.debug("No captcha modal and clean URL — solved")
        return True

    # Check body text for verification keywords
    try:
        body_text = page.locator("body").inner_text(timeout=1000).lower()
        if any(w in body_text for w in ("xác minh", "xác nhận", "kéo qua", "verification")):
            logger.debug("Verification text found in body — not solved")
            return False
    except Exception:
        pass

    return True
