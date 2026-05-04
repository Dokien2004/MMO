"""OpenCV-based slider captcha gap detector.

Uses multi-strategy voting (template matching, contour detection,
gradient column analysis) to locate the puzzle gap position.
"""
from __future__ import annotations

import logging
from dataclasses import dataclass, field
from typing import Optional

import cv2
import numpy as np

logger = logging.getLogger(__name__)


@dataclass
class DetectionResult:
    """Result of gap detection with confidence metadata."""

    x_offset: int
    y_offset: int = 0
    confidence: float = 0.0
    method: str = ""
    debug_image: Optional[np.ndarray] = field(default=None, repr=False)


def detect_gap_offset(
    background: np.ndarray,
    puzzle_piece: np.ndarray,
    debug: bool = False,
) -> DetectionResult:
    """Detect the puzzle gap X-offset using multi-strategy voting.

    Args:
        background: Background image (BGR or grayscale) containing the gap.
        puzzle_piece: Puzzle piece image (BGR or grayscale, may have alpha).
        debug: If True, attach annotated debug images to the result.

    Returns:
        DetectionResult with best estimated gap position.
    """
    bg_gray = _to_gray(background)
    piece_gray = _extract_piece_gray(puzzle_piece)

    results: list[DetectionResult] = []

    # Strategy 1: Template matching on edge images
    try:
        r = _template_match(bg_gray, piece_gray)
        logger.info("Template match: x=%d conf=%.3f", r.x_offset, r.confidence)
        results.append(r)
    except Exception as exc:
        logger.warning("Template match failed: %s", exc)

    # Strategy 2: Contour detection
    try:
        r = _contour_detect(bg_gray, piece_gray)
        logger.info("Contour detect: x=%d conf=%.3f", r.x_offset, r.confidence)
        results.append(r)
    except Exception as exc:
        logger.warning("Contour detect failed: %s", exc)

    # Strategy 3: Gradient column analysis
    try:
        r = _gradient_column(bg_gray, piece_gray)
        logger.info("Gradient column: x=%d conf=%.3f", r.x_offset, r.confidence)
        results.append(r)
    except Exception as exc:
        logger.warning("Gradient column failed: %s", exc)

    if not results:
        raise RuntimeError("All detection strategies failed")

    return _vote(results, bg_gray if debug else None)


def _template_match(bg_gray: np.ndarray, piece_gray: np.ndarray) -> DetectionResult:
    """Find puzzle piece position using cv2.matchTemplate on edge images."""
    bg_edges = cv2.Canny(cv2.GaussianBlur(bg_gray, (5, 5), 0), 50, 150)
    piece_edges = cv2.Canny(cv2.GaussianBlur(piece_gray, (5, 5), 0), 50, 150)

    # Dilate edges slightly for tolerance
    kernel = np.ones((3, 3), np.uint8)
    bg_edges = cv2.dilate(bg_edges, kernel)
    piece_edges = cv2.dilate(piece_edges, kernel)

    ph, pw = piece_edges.shape[:2]
    bh, bw = bg_edges.shape[:2]
    if pw > bw or ph > bh:
        raise ValueError("Puzzle piece is larger than background")

    result = cv2.matchTemplate(bg_edges, piece_edges, cv2.TM_CCOEFF_NORMED)
    _, max_val, _, max_loc = cv2.minMaxLoc(result)

    debug_img = cv2.cvtColor(bg_gray, cv2.COLOR_GRAY2BGR)
    cv2.rectangle(debug_img, max_loc, (max_loc[0] + pw, max_loc[1] + ph), (0, 255, 0), 2)

    return DetectionResult(
        x_offset=int(max_loc[0]),
        y_offset=int(max_loc[1]),
        confidence=float(max_val),
        method="template_match",
        debug_image=debug_img,
    )


def _contour_detect(bg_gray: np.ndarray, piece_gray: np.ndarray) -> DetectionResult:
    """Find the puzzle gap by detecting its contour shape in the background."""
    ph, pw = piece_gray.shape[:2]

    blurred = cv2.GaussianBlur(bg_gray, (5, 5), 0)
    thresh = cv2.adaptiveThreshold(
        blurred, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY_INV, 11, 2
    )

    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
    thresh = cv2.morphologyEx(thresh, cv2.MORPH_CLOSE, kernel)
    thresh = cv2.morphologyEx(thresh, cv2.MORPH_OPEN, kernel)

    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    candidates = []
    for cnt in contours:
        area = cv2.contourArea(cnt)
        x, y, w, h = cv2.boundingRect(cnt)
        # Filter by size similarity to puzzle piece
        area_ratio = area / max(pw * ph, 1)
        w_ratio = w / max(pw, 1)
        h_ratio = h / max(ph, 1)
        if 0.3 < area_ratio < 3.0 and 0.5 < w_ratio < 2.0 and 0.5 < h_ratio < 2.0:
            confidence = min(1.0, abs(1.0 - abs(w_ratio - 1.0)) * abs(1.0 - abs(h_ratio - 1.0)))
            candidates.append((x, y, w, h, confidence))

    if not candidates:
        raise RuntimeError("No puzzle-shaped contour found")

    # Pick best candidate by confidence
    best = max(candidates, key=lambda c: c[4])
    x, y, w, h, confidence = best

    debug_img = cv2.cvtColor(bg_gray, cv2.COLOR_GRAY2BGR)
    cv2.rectangle(debug_img, (x, y), (x + w, y + h), (0, 0, 255), 2)

    return DetectionResult(
        x_offset=int(x),
        y_offset=int(y),
        confidence=float(confidence),
        method="contour_detect",
        debug_image=debug_img,
    )


def _gradient_column(bg_gray: np.ndarray, piece_gray: np.ndarray) -> DetectionResult:
    """Detect the gap by analyzing vertical gradient intensity per column.

    The gap creates sharp vertical edges that produce high gradient sums
    in specific columns.
    """
    ph, pw = piece_gray.shape[:2]
    bh, bw = bg_gray.shape[:2]

    if bw < pw + 10:
        raise RuntimeError("Background too narrow for gradient analysis")

    blurred = cv2.GaussianBlur(bg_gray, (5, 5), 0)
    sobel_x = np.abs(cv2.Sobel(blurred, cv2.CV_64F, 1, 0, ksize=5))

    # Normalize
    max_val = np.max(sobel_x)
    if max_val > 1e-6:
        sobel_x = sobel_x / max_val

    # Sum gradient per column
    col_sums = np.sum(sobel_x, axis=0)

    # Smooth with kernel size ~ piece width
    kernel_size = max(4, pw // 6)
    smoothed = np.convolve(col_sums, np.ones(kernel_size) / kernel_size, mode="same")

    # Ignore edge columns
    margin = max(5, pw // 4)
    search_region = smoothed[margin : bw - margin]
    if len(search_region) == 0:
        raise RuntimeError("Background too narrow for gradient analysis")

    peak_idx = int(np.argmax(search_region)) + margin
    mean_val = float(np.mean(smoothed))
    confidence = min(10.0, float(smoothed[peak_idx] / max(mean_val, 1e-06)))

    debug_img = cv2.cvtColor(bg_gray, cv2.COLOR_GRAY2BGR)
    cv2.line(debug_img, (peak_idx, 0), (peak_idx, bh), (255, 0, 0), 2)

    return DetectionResult(
        x_offset=peak_idx,
        y_offset=0,
        confidence=confidence,
        method="gradient_column",
        debug_image=debug_img,
    )


def _vote(results: list[DetectionResult], bg_gray: np.ndarray | None) -> DetectionResult:
    """Pick the best offset from multiple detection strategies.

    If results agree (low variance), use median.
    If they diverge, prefer template matching (highest typical accuracy).
    """
    offsets = [r.x_offset for r in results]
    median_x = int(np.median(offsets))
    deviation = max(int(sum(abs(x - median_x) for x in offsets) / len(offsets)), 0)

    if deviation <= 20:
        # Good agreement — use median
        best = DetectionResult(
            x_offset=median_x,
            y_offset=results[0].y_offset,
            confidence=max(r.confidence for r in results),
            method="voted_median(" + ", ".join(r.method for r in results) + ")",
        )
        logger.info("Vote: agreement (dev=%dpx), using median x=%d", deviation, median_x)
    else:
        # Disagreement — prefer template match, then highest confidence
        template = [r for r in results if r.method == "template_match"]
        if template and template[0].confidence >= 0.4:
            best = template[0]
            best.method = f"voted_template_preferred(dev={deviation}): x={best.x_offset}"
        else:
            best = max(results, key=lambda r: r.confidence)
            best.method = f"voted_highest_conf(dev={deviation}): x={best.x_offset}"
        logger.info("Vote: disagreement (dev=%dpx), using %s x=%d", deviation, best.method, best.x_offset)

    # Build combined debug image
    if bg_gray is not None:
        debug_img = cv2.cvtColor(bg_gray, cv2.COLOR_GRAY2BGR)
        colors = [(0, 255, 0), (0, 0, 255), (255, 0, 0)]
        for i, r in enumerate(results):
            color = colors[i % len(colors)]
            cv2.line(debug_img, (r.x_offset, 0), (r.x_offset, bg_gray.shape[0]), color, 2)
            cv2.putText(
                debug_img, r.method, (5, 20 + i * 20),
                cv2.FONT_HERSHEY_SIMPLEX, 0.4, color, 1,
            )
        best.debug_image = debug_img

    return best


def _to_gray(image: np.ndarray) -> np.ndarray:
    """Convert image to grayscale if needed."""
    if len(image.shape) == 2:
        return image
    if image.shape[2] == 4:
        return cv2.cvtColor(image, cv2.COLOR_BGRA2GRAY)
    return cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)


def _extract_piece_gray(piece: np.ndarray) -> np.ndarray:
    """Extract puzzle piece grayscale, handling alpha channel.

    If the piece has an alpha channel, mask out transparent regions
    to get a clean piece shape.
    """
    if len(piece.shape) == 2:
        return piece

    if piece.shape[2] == 4:
        alpha = piece[:, :, 3]
        gray = cv2.cvtColor(piece, cv2.COLOR_BGR2GRAY)
        # Zero out transparent pixels
        gray[alpha < 128] = 0
        return gray

    return cv2.cvtColor(piece, cv2.COLOR_BGR2GRAY)


def save_debug_image(result: DetectionResult, path: str) -> None:
    """Save debug visualization to disk."""
    if result.debug_image is not None:
        cv2.imwrite(str(path), result.debug_image)
        logger.info("Debug image saved to %s", path)
