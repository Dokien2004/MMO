"""Automated Shopee login via Playwright.

Handles the complete login flow:
1. Navigate to Shopee login page
2. Enter phone/email + password
3. Handle slider captcha (via CV solver)
4. Wait for OTP if 2FA is required
5. Verify login success
"""
from __future__ import annotations

import logging
import os
import random
import time
from pathlib import Path
from typing import Any, Optional

logger = logging.getLogger(__name__)

SHOPEE_LOGIN_URL = "https://shopee.vn/buyer/login"
SHOPEE_HOME_URL = "https://shopee.vn/"

# Selector groups for login page elements
LOGIN_SELECTORS: dict[str, tuple[str, ...]] = {
    "username_input": (
        "input[name='loginKey']",
        "input[type='text'][autocomplete='username']",
        "input[placeholder*='Số điện thoại']",
        "input[placeholder*='Email']",
        "input[placeholder*='Phone']",
        ".input-with-validator input[type='text']",
        "form input[type='text']:first-of-type",
    ),
    "password_input": (
        "input[name='password']",
        "input[type='password']",
        "input[autocomplete='current-password']",
        ".input-with-validator input[type='password']",
    ),
    "login_button": (
        "button[type='submit']",
        "button.btn-solid-primary",
        "button:has-text('Đăng nhập')",
        "button:has-text('Log In')",
        "form button:last-of-type",
    ),
    "otp_input": (
        "input[name='otp']",
        "input[placeholder*='Mã xác minh']",
        "input[placeholder*='Verification']",
        "input[type='tel']",
        ".otp-input input",
    ),
    "login_success_indicator": (
        ".navbar__username",
        "[class*='navbar'] [class*='user']",
        ".shopee-avatar",
        "a[href*='/user/account']",
    ),
}


def load_credentials_from_env(env_path: Optional[str] = None) -> dict[str, str]:
    """Load Shopee credentials from .env file or environment variables.

    Checks (in order):
    1. Explicit env_path file
    2. .env file in project root
    3. .env.example file in project root
    4. OS environment variables
    """
    base_path = Path(__file__).resolve().parents[2]
    env_files = []

    if env_path:
        env_files.append(Path(env_path))
    env_files.extend([
        base_path / ".env",
        base_path / ".env.local",
        base_path / ".env.example",
    ])

    env_vars: dict[str, str] = {}
    for env_file in env_files:
        if env_file.exists():
            parsed = _parse_env_file(env_file)
            if parsed.get("SHOPEE_USERNAME") and parsed.get("SHOPEE_PASSWORD"):
                env_vars = parsed
                logger.info("Loaded Shopee credentials from %s", env_file)
                break

    username = env_vars.get("SHOPEE_USERNAME") or os.environ.get("SHOPEE_USERNAME", "")
    password = env_vars.get("SHOPEE_PASSWORD") or os.environ.get("SHOPEE_PASSWORD", "")
    profile_dir = env_vars.get("SHOPEE_PROFILE_DIR") or os.environ.get(
        "SHOPEE_PROFILE_DIR", str(base_path / "storage/browser/shopee-profile")
    )

    return {
        "username": username.strip(),
        "password": password,
        "profile_dir": profile_dir,
    }


def _parse_env_file(path: Path) -> dict[str, str]:
    """Parse a simple .env file (KEY=VALUE format)."""
    result: dict[str, str] = {}
    try:
        for line in path.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
                value = value[1:-1]
            result[key] = value
    except Exception as exc:
        logger.warning("Failed to parse %s: %s", path, exc)
    return result


def auto_login(
    page: Any,
    username: str = "",
    password: str = "",
    env_path: Optional[str] = None,
    handle_captcha: bool = True,
    otp_timeout_seconds: int = 120,
    captcha_debug_dir: Optional[str] = None,
) -> bool:
    """Perform automated Shopee login.

    Args:
        page: Playwright Page object.
        username: Shopee phone number or email. If empty, loads from .env.
        password: Shopee password. If empty, loads from .env.
        env_path: Path to .env file (optional).
        handle_captcha: Whether to auto-solve slider captcha during login.
        otp_timeout_seconds: How long to wait for manual OTP entry.
        captcha_debug_dir: Directory to save captcha debug images.

    Returns:
        True if login succeeded, False otherwise.
    """
    if not username or not password:
        creds = load_credentials_from_env(env_path)
        username = username or creds.get("username", "")
        password = password or creds.get("password", "")

    if not username or not password:
        logger.error(
            "Shopee credentials not found. Set SHOPEE_USERNAME and "
            "SHOPEE_PASSWORD in .env or pass them as arguments."
        )
        return False

    logger.info("Starting Shopee auto-login for user: %s", _mask_username(username))

    try:
        # Check if already logged in
        if _is_already_logged_in(page):
            logger.info("Already logged in!")
            return True

        # Navigate to login page with retry
        login_page_loaded = False
        for wait_strategy in ("domcontentloaded", "commit"):
            try:
                page.goto(SHOPEE_LOGIN_URL, wait_until=wait_strategy, timeout=60000)
                time.sleep(random.uniform(2.0, 3.5))
                login_page_loaded = True
                break
            except Exception as exc:
                logger.warning("Login page load failed (wait=%s): %s", wait_strategy, exc)

        if not login_page_loaded:
            logger.error("Failed to navigate to login page after retries")
            return False

        # Check if redirected away from login
        current_url = (getattr(page, "url", "") or "").lower()
        if "buyer/login" not in current_url and "verify" not in current_url:
            logger.info("Redirected away from login — may be already logged in")
            return True

        # Enter credentials
        if not _enter_credentials(page, username, password):
            logger.error("Failed to enter credentials")
            return False

        # Click login button
        if not _click_login_button(page):
            logger.error("Failed to click login button")
            return False

        time.sleep(random.uniform(2.0, 4.0))

        # Check for captcha (multiple rounds)
        for check_round in range(5):
            if _is_already_logged_in(page):
                logger.info("Login successful!")
                return True

            if handle_captcha and _has_slider_captcha(page):
                logger.info("Slider captcha detected during login, solving...")
                try:
                    from . import playwright_handler

                    solve_captcha = playwright_handler.solve_captcha
                    solved = solve_captcha(page, platform="shopee", max_attempts=3, debug_dir=captcha_debug_dir)

                    if solved:
                        logger.info("Login captcha solved!")
                        time.sleep(random.uniform(1.5, 3.0))
                    else:
                        logger.warning("Failed to solve login captcha")
                        return False
                except Exception as exc:
                    logger.warning("Captcha solver error: %s", exc)

            if _has_otp_input(page):
                logger.info("OTP/2FA required. Waiting up to %ds for manual OTP entry...", otp_timeout_seconds)
                otp_entered = _wait_for_otp_completion(page, otp_timeout_seconds)
                if otp_entered:
                    logger.info("OTP entered, verifying login...")
                    time.sleep(random.uniform(1.5, 3.0))
                else:
                    logger.error("OTP timeout — login failed")
                    return False

            time.sleep(1.0)

        # Final check
        if _is_already_logged_in(page):
            logger.info("Login successful!")
            return True

        error_msg = _get_login_error(page)
        if error_msg:
            logger.error("Login error: %s", error_msg)

        return False

    except Exception as exc:
        logger.error("Login error: %s", exc)
        return False


def check_and_login_if_needed(page: Any, **login_kwargs: Any) -> bool:
    """Check if logged in, attempt auto-login if not.

    Convenience wrapper that:
    1. Visits Shopee homepage
    2. Checks login status
    3. If not logged in, performs auto_login()
    4. Returns to homepage after login

    Returns True if logged in (was already or just logged in).
    """
    try:
        page.goto(SHOPEE_HOME_URL, wait_until="domcontentloaded", timeout=60000)
        time.sleep(random.uniform(1.5, 3.0))
    except Exception:
        pass

    if _is_already_logged_in(page):
        logger.info("Session is active — already logged in")
        return True

    logger.info("Not logged in — attempting auto-login...")
    result = auto_login(page, **login_kwargs)

    # Navigate back to homepage
    try:
        page.goto(SHOPEE_HOME_URL, wait_until="domcontentloaded", timeout=30000)
        time.sleep(random.uniform(1.5, 2.5))
    except Exception:
        pass

    return result


def _find_element(page: Any, selectors: list[str] | tuple[str, ...], timeout: int = 2000) -> Any:
    """Try multiple selectors, return first visible match."""
    for selector in selectors:
        try:
            locator = page.locator(selector).first
            if locator.is_visible(timeout=timeout):
                return locator
        except Exception:
            continue
    return None


def _enter_credentials(page: Any, username: str, password: str) -> bool:
    """Type username and password into login form."""
    username_el = _find_element(page, LOGIN_SELECTORS["username_input"])
    if not username_el:
        logger.error("Username input not found")
        return False

    username_el.click()
    time.sleep(random.uniform(0.2, 0.5))
    username_el.fill("")
    time.sleep(random.uniform(0.1, 0.3))
    _human_type(page, username_el, username)
    time.sleep(random.uniform(0.8, 1.0))

    password_el = _find_element(page, LOGIN_SELECTORS["password_input"])
    if not password_el:
        logger.error("Password input not found")
        return False

    password_el.click()
    time.sleep(random.uniform(0.2, 0.5))
    password_el.fill("")
    time.sleep(random.uniform(0.1, 0.3))
    _human_type(page, password_el, password)

    return True


def _human_type(page: Any, element: Any, text: str) -> None:
    """Type text character by character with random delays."""
    for char in text:
        element.press(char)
        if len(text) > 1:
            delay = random.uniform(0.05, 0.15)
            time.sleep(delay)
    time.sleep(random.uniform(0.1, 0.3))


def _click_login_button(page: Any) -> bool:
    """Find and click the login/submit button."""
    button = _find_element(page, LOGIN_SELECTORS["login_button"])
    if not button:
        logger.error("Login button not found")
        return False

    # Try natural click position
    try:
        box = button.bounding_box()
        if box:
            page.mouse.move(
                int(box["x"] + box["width"] / 2 + random.randint(-5, 5)),
                int(box["y"] + box["height"] / 2 + random.randint(-3, 3)),
            )
            time.sleep(random.uniform(0.1, 0.3))
    except Exception:
        pass

    button.click()
    return True


def _is_already_logged_in(page: Any) -> bool:
    """Check if user is currently logged into Shopee."""
    url = (getattr(page, "url", "") or "").lower()
    if "buyer/login" in url:
        return False

    # Check for logged-in indicators
    for selector in LOGIN_SELECTORS["login_success_indicator"]:
        try:
            locator = page.locator(selector).first
            if locator.is_visible(timeout=1500):
                return True
        except Exception:
            continue

    # Check auth cookies
    try:
        cookies = page.context.cookies()
        auth_cookies = frozenset({"SPC_ST", "SPC_U", "SPC_EC"})
        found = [c for c in cookies if c.get("name") in auth_cookies]
        if len(found) >= 2:
            logger.debug("Auth cookies found: %s", [c["name"] for c in found])
            return True
    except Exception:
        pass

    return False


def _has_slider_captcha(page: Any) -> bool:
    """Check if a slider captcha is present on the login page."""
    captcha_selectors = (
        "[class*='captcha']",
        "[class*='Captcha']",
        "[class*='slider-verify']",
        ".verify-wrap",
    )
    for selector in captcha_selectors:
        try:
            locator = page.locator(selector).first
            if locator.is_visible(timeout=800):
                return True
        except Exception:
            continue
    return False


def _has_otp_input(page: Any) -> bool:
    """Check if OTP/2FA input is visible."""
    return _find_element(page, LOGIN_SELECTORS["otp_input"], timeout=1000) is not None


def _wait_for_otp_completion(page: Any, timeout_seconds: int) -> bool:
    """Wait for OTP entry and submission to complete.

    Monitors the page URL — when it changes away from verify/otp pages,
    the OTP flow is complete.
    """
    import sys

    print(
        "\n╔══════════════════════════════════════════════════════╗\n"
        "║  📱 OTP Required!                                    ║\n"
        "║  Check your phone for the Shopee verification code.  ║\n"
        "║  Enter it in the browser, then wait...               ║\n"
        "╚══════════════════════════════════════════════════════╝\n",
        file=sys.stderr,
    )

    start = time.time()
    while time.time() - start < timeout_seconds:
        if _is_already_logged_in(page):
            return True

        url = (getattr(page, "url", "") or "").lower()
        if "buyer/login" not in url and "verify" not in url and "otp" not in url:
            return True

        time.sleep(2.0)

    return False


def _get_login_error(page: Any) -> str:
    """Check for error messages on the login page."""
    error_selectors = (
        ".shopee-authen--error",
        "[class*='error-msg']",
        "[class*='Error']",
        ".input-with-validator__error-text",
    )
    for selector in error_selectors:
        try:
            el = page.locator(selector).first
            if el.is_visible(timeout=500):
                return el.inner_text(timeout=500).strip()
        except Exception:
            continue
    return ""


def _mask_username(username: str) -> str:
    """Mask username for safe logging (show first 3 and last 2 chars)."""
    if len(username) <= 5:
        return username[:3] + "*" * (len(username) - 3)
    return username[:3] + "***" + username[-2:]
