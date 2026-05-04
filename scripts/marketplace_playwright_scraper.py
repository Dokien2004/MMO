#!/usr/bin/env python3
"""Slow Playwright scraper using network response capture.

The script writes normalized products compatible with ProductSyncService:
source_product_id, product_name, product_url, price, sold_count, notes.
"""

from __future__ import annotations

import argparse
import json
import os
import random
import re
import sys
import time
from pathlib import Path
from typing import Any, Mapping
from urllib.parse import quote, quote_plus


BASE_PATH = Path(__file__).resolve().parents[1]

SHOPEE_CATEGORY_NAMES = {
    11036132: "Điện tử",
    11036030: "Máy tính & Laptop",
    11036670: "Nhà cửa & Đời sống",
    11035567: "Thời Trang Nam",
    11035639: "Thời Trang Nữ",
    11036279: "Sức khỏe & Sắc đẹp",
    11036525: "Bách hóa online",
    11036594: "Phụ kiện & Trang sức",
    11036915: "Đồ chơi",
    11036101: "Thiết bị điện gia dụng",
    11035853: "Giày dép nam",
    11035801: "Giày dép nữ",
    11036382: "Mẹ & Bé",
    11036812: "Thể thao & Du lịch",
}

SHOPEE_LOGIN_URL = "https://shopee.vn/buyer/login"
SHOPEE_USERNAME_SELECTORS = [
    'input[name="loginKey"]',
    'input[autocomplete="username"]',
    'input[type="email"]',
    'input[type="tel"]',
    'input[type="text"]',
]
SHOPEE_PASSWORD_SELECTORS = [
    'input[name="password"]',
    'input[autocomplete="current-password"]',
    'input[type="password"]',
]
SHOPEE_LOGIN_BUTTON_SELECTORS = [
    'button[type="submit"]',
    'button:has-text("Đăng nhập")',
    'button:has-text("Log in")',
    'button:has-text("LOGIN")',
]


def headed_linux_without_display(headed: bool, platform_name: str, environ: dict[str, str]) -> bool:
    return headed and platform_name.startswith("linux") and not environ.get("DISPLAY")


def should_capture_url(platform: str, url: str) -> bool:
    platform = platform.lower()
    lowered = url.lower()
    if platform == "shopee":
        return (
            "/api/v4/search/search_items" in lowered
            or "/api/v4/recommend/recommend" in lowered
            or "/api/v4/pdp/recommend" in lowered
        )
    if platform == "lazada":
        return (
            "ajax=true" in lowered and ("lazada.vn/catalog" in lowered or "/catalog/" in lowered)
        ) or "mtop.lazada.search" in lowered
    return False


def normalize_payload(platform: str, payload: dict[str, Any], source: str) -> list[dict[str, Any]]:
    platform = platform.lower()
    if platform == "shopee":
        return normalize_shopee_payload(payload, source)
    if platform == "lazada":
        return normalize_lazada_payload(payload, source)
    raise ValueError(f"Unsupported platform: {platform}")


def normalize_shopee_payload(payload: dict[str, Any], source: str) -> list[dict[str, Any]]:
    raw_items: list[Any] = []

    if isinstance(payload.get("items"), list):
        raw_items.extend(payload["items"])
    if isinstance(payload.get("item_basic"), list):
        raw_items.extend(payload["item_basic"])

    sections = (((payload.get("data") or {}).get("sections")) or [])
    if isinstance(sections, list):
        for section in sections:
            items = (((section or {}).get("data") or {}).get("item")) or []
            if isinstance(items, list):
                raw_items.extend(items)

    products: list[dict[str, Any]] = []
    for raw_item in raw_items:
        if not isinstance(raw_item, dict):
            continue
        info = raw_item.get("item_basic") if isinstance(raw_item.get("item_basic"), dict) else raw_item
        shop_id = to_int(info.get("shopid"))
        item_id = to_int(info.get("itemid"))
        name = str(info.get("name") or "").strip()
        if item_id <= 0 or not name:
            continue

        products.append(
            {
                "source_product_id": f"SH-{shop_id}-{item_id}",
                "product_name": name,
                "product_url": f"https://shopee.vn/product/{shop_id}/{item_id}",
                "price": float(to_int(info.get("price")) / 100000),
                "sold_count": max(to_int(info.get("sold")), to_int(info.get("historical_sold"))),
                "notes": source,
            }
        )

    return products


def build_shopee_category_url(category_id: int, page: int = 0, category_name: str = "") -> str:
    name = (category_name or SHOPEE_CATEGORY_NAMES.get(int(category_id)) or f"cat-{category_id}").strip()
    slug = re.sub(r"\s+", "-", name)
    slug = re.sub(r"-+", "-", slug).strip("-")
    encoded_slug = quote(slug, safe="-")
    safe_page = max(0, int(page))
    return f"https://shopee.vn/{encoded_slug}-cat.{int(category_id)}?page={safe_page}&sortBy=sales"


def extract_shopee_product_ids(url: str) -> tuple[int, int] | None:
    for pattern in (r"-i\.(\d+)\.(\d+)", r"/product/(\d+)/(\d+)"):
        match = re.search(pattern, url)
        if match:
            return int(match.group(1)), int(match.group(2))
    return None


def parse_shopee_price_from_text(text: str) -> float:
    match = re.search(r"₫\s*([0-9][0-9.,]*)", text)
    return parse_price(match.group(1)) if match else 0.0


def parse_shopee_sold_count_from_text(text: str) -> int:
    normalized = text.lower()
    for pattern in (
        r"(?:đã\s*bán|da\s*ban|sold)\s*([0-9]+(?:[,.][0-9]+)?\s*[km]?)",
        r"([0-9]+(?:[,.][0-9]+)?\s*[km]?)\s*(?:đã\s*bán|da\s*ban|sold)",
    ):
        match = re.search(pattern, normalized)
        if match:
            return parse_compact_number(match.group(1))
    return 0


def infer_shopee_dom_name(card: dict[str, Any]) -> str:
    for key in ("name", "imageAlt", "ariaLabel"):
        candidate = normalize_text(str(card.get(key) or ""))
        if candidate and not candidate.lower().startswith(("image", "ảnh")):
            return candidate

    ignored_tokens = (
        "₫",
        "đã bán",
        "da ban",
        "yêu thích",
        "mall",
        "freeship",
        "voucher",
        "giảm",
        "tài trợ",
    )
    for line in str(card.get("text") or "").splitlines():
        candidate = normalize_text(line)
        lowered = candidate.lower()
        if len(candidate) >= 5 and not any(token in lowered for token in ignored_tokens):
            return candidate
    return ""


def normalize_text(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def normalize_shopee_dom_cards(cards: list[dict[str, Any]], source: str) -> list[dict[str, Any]]:
    products: list[dict[str, Any]] = []
    for card in cards:
        href = str(card.get("href") or "")
        product_ids = extract_shopee_product_ids(href)
        if product_ids is None:
            continue
        shop_id, item_id = product_ids
        name = infer_shopee_dom_name(card)
        if not name:
            continue

        text = str(card.get("text") or "")
        products.append(
            {
                "source_product_id": f"SH-{shop_id}-{item_id}",
                "product_name": name,
                "product_url": f"https://shopee.vn/product/{shop_id}/{item_id}",
                "price": parse_shopee_price_from_text(text),
                "sold_count": parse_shopee_sold_count_from_text(text),
                "notes": source,
            }
        )
    return products


def normalize_lazada_payload(payload: dict[str, Any], source: str) -> list[dict[str, Any]]:
    items = extract_lazada_items(payload)

    products: list[dict[str, Any]] = []
    for item in items:
        if not isinstance(item, dict):
            continue
        item_id = str(item.get("nid") or item.get("itemId") or "").strip()
        name = str(item.get("name") or "").strip()
        if not item_id or not name:
            continue

        product_url = normalize_url(str(item.get("productUrl") or f"https://www.lazada.vn/-i{item_id}.html"))
        sold_count = max(
            to_int(item.get("sold")),
            parse_compact_number(str(item.get("itemSoldCntShow") or "")),
        )

        products.append(
            {
                "source_product_id": f"LZ-{item_id}",
                "product_name": name,
                "product_url": product_url,
                "price": parse_price(item.get("price")),
                "sold_count": sold_count,
                "notes": source,
            }
        )

    return products


def extract_lazada_items(payload: dict[str, Any]) -> list[Any]:
    candidates = [
        ((payload.get("mods") or {}).get("listItems")),
        (((payload.get("data") or {}).get("mods") or {}).get("listItems")),
        ((((payload.get("data") or {}).get("root") or {}).get("fields") or {}).get("listItems")),
        (((((payload.get("data") or {}).get("data") or {}).get("root") or {}).get("fields") or {}).get("listItems")),
    ]

    for candidate in candidates:
        parsed = parse_lazada_list_items(candidate)
        if parsed:
            return parsed

    return []


def parse_lazada_list_items(value: Any) -> list[Any]:
    if isinstance(value, list):
        return value
    if isinstance(value, str) and value.strip():
        try:
            decoded = json.loads(value)
        except json.JSONDecodeError:
            return []
        return decoded if isinstance(decoded, list) else []
    return []


def normalize_url(url: str) -> str:
    url = url.strip()
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return "https://www.lazada.vn" + url
    return url


def parse_price(value: Any) -> float:
    if isinstance(value, (int, float)):
        return float(value)
    text = str(value or "").strip()
    if not text:
        return 0.0
    cleaned = re.sub(r"[^0-9,.]", "", text)
    if not cleaned:
        return 0.0
    if "," in cleaned and "." in cleaned:
        last_comma = cleaned.rfind(",")
        last_dot = cleaned.rfind(".")
        decimal_separator = "," if last_comma > last_dot else "."
        if len(cleaned) - max(last_comma, last_dot) - 1 == 3:
            cleaned = cleaned.replace(".", "").replace(",", "")
        elif decimal_separator == ",":
            cleaned = cleaned.replace(".", "").replace(",", ".")
        else:
            cleaned = cleaned.replace(",", "")
    elif "." in cleaned:
        parts = cleaned.split(".")
        cleaned = "".join(parts) if len(parts[-1]) == 3 else cleaned
    elif "," in cleaned:
        parts = cleaned.split(",")
        cleaned = "".join(parts) if len(parts[-1]) == 3 else cleaned.replace(",", ".")
    try:
        return float(cleaned)
    except ValueError:
        return 0.0


def parse_compact_number(text: str) -> int:
    normalized = text.lower().replace(",", ".")
    match = re.search(r"([0-9]+(?:\.[0-9]+)?)\s*([km]?)", normalized)
    if not match:
        return 0
    number = float(match.group(1))
    suffix = match.group(2)
    if suffix == "k":
        number *= 1000
    elif suffix == "m":
        number *= 1000000
    return int(number)


def to_int(value: Any) -> int:
    if isinstance(value, bool):
        return int(value)
    if isinstance(value, int):
        return value
    if isinstance(value, float):
        return int(value)
    return parse_compact_number(str(value or ""))


def dedupe_products(products: list[dict[str, Any]]) -> list[dict[str, Any]]:
    indexed: dict[str, dict[str, Any]] = {}
    for product in products:
        key = str(product.get("source_product_id") or "")
        if key and key not in indexed:
            indexed[key] = product
    return list(indexed.values())


def build_start_url(platform: str, keyword: str) -> str:
    encoded = quote_plus(keyword)
    if platform == "shopee":
        return f"https://shopee.vn/search?keyword={encoded}&sortBy=sales"
    if platform == "lazada":
        return f"https://www.lazada.vn/catalog/?q={encoded}&sort=pop"
    raise ValueError(f"Unsupported platform: {platform}")


def parse_env_text(text: str) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in text.splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        if line.startswith("export "):
            line = line[len("export ") :].strip()
        key, value = line.split("=", 1)
        key = key.strip()
        if not re.match(r"^[A-Za-z_][A-Za-z0-9_]*$", key):
            continue
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]
        values[key] = value
    return values


def load_shopee_credentials(environ: Mapping[str, str], env_file: Path) -> tuple[str, str]:
    file_values: dict[str, str] = {}
    try:
        if env_file.exists():
            file_values = parse_env_text(env_file.read_text(encoding="utf-8"))
    except OSError:
        file_values = {}

    username = (environ.get("SHOPEE_USERNAME") or file_values.get("SHOPEE_USERNAME") or "").strip()
    password = environ.get("SHOPEE_PASSWORD") or file_values.get("SHOPEE_PASSWORD") or ""
    return username, password


def fill_first_available(page: Any, selectors: list[str], value: str, timeout_ms: int) -> bool:
    for selector in selectors:
        try:
            locator = page.locator(selector)
            if locator.is_visible(timeout=timeout_ms):
                locator.fill(value, timeout=timeout_ms)
                return True
        except Exception:
            continue
    return False


def click_first_available(page: Any, selectors: list[str], timeout_ms: int) -> bool:
    for selector in selectors:
        try:
            locator = page.locator(selector)
            if locator.is_visible(timeout=timeout_ms):
                locator.click(timeout=timeout_ms)
                return True
        except Exception:
            continue
    return False


def run_shopee_login_assist(page: Any, username: str, password: str, timeout_ms: int) -> bool:
    if not username or not password:
        return False

    page.goto(SHOPEE_LOGIN_URL, wait_until="domcontentloaded", timeout=timeout_ms)
    username_filled = fill_first_available(page, SHOPEE_USERNAME_SELECTORS, username, timeout_ms)
    password_filled = fill_first_available(page, SHOPEE_PASSWORD_SELECTORS, password, timeout_ms)
    if not username_filled or not password_filled:
        return False
    return click_first_available(page, SHOPEE_LOGIN_BUTTON_SELECTORS, timeout_ms)


def build_home_url(platform: str) -> str:
    if platform == "shopee":
        return "https://shopee.vn/"
    if platform == "lazada":
        return "https://www.lazada.vn/"
    raise ValueError(f"Unsupported platform: {platform}")


def apply_stealth_if_available(page: Any) -> bool:
    try:
        import playwright_stealth  # type: ignore
    except Exception:
        return False

    stealth_sync = getattr(playwright_stealth, "stealth_sync", None)
    if callable(stealth_sync):
        stealth_sync(page)
        return True

    stealth_class = getattr(playwright_stealth, "Stealth", None)
    if stealth_class is not None:
        stealth = stealth_class()
        apply_sync = getattr(stealth, "apply_stealth_sync", None)
        if callable(apply_sync):
            apply_sync(page)
            return True

    return False


def normalize_browser_channel(channel: str | None) -> str:
    normalized = str(channel or "").strip().lower()
    if normalized in {"", "none", "bundled", "playwright", "chromium"}:
        return ""
    return normalized


def build_browser_channel_attempts(channel: str | None) -> list[str]:
    first = normalize_browser_channel(channel)
    if first == "":
        return [""]
    return [first, ""]


def find_profile_lock_files(user_data_dir: Path) -> list[Path]:
    candidates = [
        user_data_dir / "SingletonLock",
        user_data_dir / "SingletonSocket",
        user_data_dir / "SingletonCookie",
        user_data_dir / "Default" / "LOCK",
    ]
    return [path for path in candidates if path.exists()]


def looks_like_captcha(page: Any) -> bool:
    url = (getattr(page, "url", "") or "").lower()
    if "captcha" in url or "punish" in url or "/verify/traffic" in url:
        return True
    try:
        body_text = page.locator("body").inner_text(timeout=1000).lower()
    except Exception:
        return False
    return (
        "captcha" in body_text
        or "xác minh" in body_text
        or "xác nhận" in body_text
        or "kéo qua" in body_text
        or "verification" in body_text
    )


def safe_filename_token(value: str) -> str:
    token = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return token or "marketplace"


def build_captcha_artifact_paths(base_dir: Path, platform: str, timestamp: int | None = None) -> dict[str, Path]:
    safe_platform = safe_filename_token(platform)
    suffix = int(timestamp if timestamp is not None else time.time())
    stem = f"{safe_platform}_captcha_{suffix}"
    return {
        "screenshot": base_dir / f"{stem}.png",
        "metadata": base_dir / f"{stem}.json",
    }


def read_page_body_text(page: Any, limit: int = 1200) -> str:
    try:
        return str(page.locator("body").inner_text(timeout=1000))[:limit]
    except Exception:
        return ""


def capture_captcha_artifacts(
    page: Any,
    output_dir: Path,
    platform: str,
    screenshot_timeout_ms: int = 5000,
) -> dict[str, str]:
    paths = build_captcha_artifact_paths(output_dir, platform)
    paths["metadata"].parent.mkdir(parents=True, exist_ok=True)

    screenshot_error = ""
    try:
        page.screenshot(
            path=str(paths["screenshot"]),
            full_page=False,
            timeout=screenshot_timeout_ms,
            animations="disabled",
        )
    except Exception as error:
        screenshot_error = str(error)

    metadata = {
        "platform": platform,
        "url": str(getattr(page, "url", "")),
        "title": page.title() if hasattr(page, "title") else "",
        "body_text": read_page_body_text(page),
        "screenshot": str(paths["screenshot"]) if not screenshot_error else "",
        "screenshot_error": screenshot_error,
        "created_at": int(time.time()),
    }
    write_json(paths["metadata"], metadata)

    return {
        "screenshot": metadata["screenshot"],
        "metadata": str(paths["metadata"]),
        "screenshot_error": screenshot_error,
    }


def extract_shopee_dom_cards(page: Any) -> list[dict[str, Any]]:
    cards = page.evaluate(
        """
        () => {
          const productPattern = /(?:-i\\.\\d+\\.\\d+|\\/product\\/\\d+\\/\\d+)/;
          const seen = new Set();
          const anchors = [...document.querySelectorAll('a[href]')]
            .filter((anchor) => productPattern.test(anchor.href));

          const normalize = (value) => String(value || '').trim().replace(/\\s+/g, ' ');
          const results = [];
          for (const anchor of anchors) {
            const productKey = anchor.href.match(/(?:-i\\.(\\d+)\\.(\\d+)|\\/product\\/(\\d+)\\/(\\d+))/)?.[0];
            if (!productKey || seen.has(productKey)) {
              continue;
            }
            seen.add(productKey);

            const card =
              anchor.closest('li') ||
              anchor.closest('[data-sqe="item"]') ||
              anchor.closest('[data-testid*="item"]') ||
              anchor.parentElement ||
              anchor;
            const image = anchor.querySelector('img') || card.querySelector('img');

            results.push({
              href: anchor.href,
              text: (card.innerText || anchor.innerText || anchor.textContent || '').trim(),
              imageAlt: normalize(image ? image.getAttribute('alt') : ''),
              ariaLabel: normalize(anchor.getAttribute('aria-label')),
            });
          }
          return results;
        }
        """
    )
    return cards if isinstance(cards, list) else []


def write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")


def append_jsonl(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(payload, ensure_ascii=False) + "\n")


def run_browser(args: argparse.Namespace) -> int:
    try:
        from playwright.sync_api import sync_playwright  # type: ignore
    except Exception:
        print(
            "Missing dependency: playwright. Install with:\n"
            "  python3 -m pip install -r requirements-scraper.txt\n"
            "  python3 -m playwright install chromium",
            file=sys.stderr,
        )
        return 2

    if headed_linux_without_display(args.headed, sys.platform, os.environ):
        print(
            "Headed mode on Linux needs a DISPLAY. Run with Xvfb or a real desktop/VNC, for example:\n"
            "  xvfb-run -a python3 scripts/marketplace_playwright_scraper.py --headed "
            "--platform shopee --keyword \"laptop\" --warmup-session",
            file=sys.stderr,
        )
        return 2

    platform = args.platform.lower()
    if args.url:
        start_url = args.url
    elif platform == "shopee" and args.shopee_category_id:
        start_url = build_shopee_category_url(
            args.shopee_category_id,
            args.category_page,
            args.shopee_category_name,
        )
    else:
        start_url = build_start_url(platform, args.keyword)
    output_file = Path(args.output)
    raw_file = Path(args.raw_output) if args.raw_output else None
    user_data_dir = Path(args.user_data_dir)
    captcha_artifact_dir = Path(args.captcha_artifact_dir)
    env_file = Path(args.env_file)
    products: list[dict[str, Any]] = []
    errors: list[str] = []
    captured_count = 0
    html_card_count = 0
    final_url = start_url

    try:
        user_data_dir.mkdir(parents=True, exist_ok=True)
    except OSError as error:
        print(f"Cannot create user data dir {user_data_dir}: {error}", file=sys.stderr)
        return 2

    def handle_response(response: Any) -> None:
        nonlocal captured_count
        if not should_capture_url(platform, response.url):
            return
        try:
            if response.status >= 400:
                return
            payload = response.json()
        except Exception:
            return
        if not isinstance(payload, dict):
            return

        captured_count += 1
        if raw_file is not None:
            append_jsonl(raw_file, {"url": response.url, "payload": payload})

        if payload.get("error"):
            errors.append(f"{response.url}: marketplace error {payload.get('error')}")
            return

        source = f"{platform} network: {response.url}"
        try:
            products.extend(normalize_payload(platform, payload, source))
        except Exception as error:
            message = f"Skip malformed payload from {response.url}: {error}"
            errors.append(message)
            print(message, file=sys.stderr)

    with sync_playwright() as playwright:
        base_launch_options: dict[str, Any] = {
            "headless": args.headless,
            "args": [
                "--disable-blink-features=AutomationControlled",
                "--disable-dev-shm-usage",
                "--lang=vi-VN",
            ],
        }

        context = None
        launch_errors: list[str] = []
        for channel in build_browser_channel_attempts(args.browser_channel):
            launch_options = dict(base_launch_options)
            if channel:
                launch_options["channel"] = channel
            try:
                context = playwright.chromium.launch_persistent_context(
                    user_data_dir=str(user_data_dir),
                    viewport={"width": 1365, "height": 900},
                    locale="vi-VN",
                    user_agent=args.user_agent,
                    **launch_options,
                )
                if channel == "" and normalize_browser_channel(args.browser_channel) != "":
                    print(
                        "Browser channel failed; retried with Playwright bundled Chromium.",
                        file=sys.stderr,
                    )
                break
            except Exception as error:
                label = channel or "bundled Chromium"
                launch_errors.append(f"{label}: {error}")
                if channel:
                    print(
                        f"Browser launch failed with channel '{channel}', retrying bundled Chromium...",
                        file=sys.stderr,
                    )

        if context is None:
            print("Browser launch failed.", file=sys.stderr)
            for error in launch_errors:
                print(error, file=sys.stderr)
            print("Try: python3 -m playwright install chromium", file=sys.stderr)
            return 2

        page = context.new_page()
        apply_stealth_if_available(page)

        if args.shopee_login_assist:
            if platform != "shopee":
                print("--shopee-login-assist is only used with --platform shopee; skipping.", file=sys.stderr)
            else:
                username, password = load_shopee_credentials(os.environ, env_file)
                if not username or not password:
                    print(
                        "Shopee login assist is enabled but SHOPEE_USERNAME/SHOPEE_PASSWORD are missing. "
                        f"Set them in environment or {env_file}.",
                        file=sys.stderr,
                    )
                else:
                    try:
                        print("Shopee login assist: filling credentials from local environment.", file=sys.stderr)
                        submitted = run_shopee_login_assist(page, username, password, args.timeout_ms)
                        if not submitted:
                            errors.append("Shopee login assist could not find or submit the login form.")
                        time.sleep(random.uniform(2.0, 4.0))

                        if looks_like_captcha(page):
                            message = "Captcha/verification detected after Shopee login assist."
                            artifacts = capture_captcha_artifacts(
                                page,
                                captcha_artifact_dir,
                                platform,
                                args.captcha_screenshot_timeout_ms,
                            )
                            artifact_note = (
                                f"metadata: {artifacts['metadata']}; "
                                f"screenshot: {artifacts['screenshot'] or artifacts['screenshot_error']}"
                            )
                            errors.append(f"{message} Current URL: {page.url}; {artifact_note}")
                            if args.pause_on_captcha:
                                print(
                                    message + " Solve manually, then press Enter. " + artifact_note,
                                    file=sys.stderr,
                                )
                                input()
                            else:
                                print(
                                    message
                                    + " Automatic captcha solving is not supported; rerun with "
                                    + "--headed --pause-on-captcha. "
                                    + artifact_note,
                                    file=sys.stderr,
                                )
                    except Exception as error:
                        errors.append(f"Shopee login assist failed: {error}")

        if args.warmup_session:
            warmup_url = args.warmup_url or build_home_url(platform)
            print(
                "Warm-up session is open. Login/solve verification in the browser, then return here.",
                file=sys.stderr,
            )
            page.goto(warmup_url, wait_until="domcontentloaded", timeout=args.timeout_ms)
            if args.playwright_pause:
                page.pause()
            input("Press Enter after the browser session is clean...")
        page.on("response", handle_response)

        # Navigate to target URL with retry (Shopee may abort/redirect)
        nav_success = False
        for nav_attempt in range(3):
            try:
                page.goto(start_url, wait_until="domcontentloaded", timeout=args.timeout_ms)
                nav_success = True
                break
            except Exception as nav_err:
                err_str = str(nav_err).lower()
                if "err_aborted" in err_str or "net::" in err_str:
                    print(
                        f"Navigation attempt {nav_attempt + 1} aborted, retrying after delay...",
                        file=sys.stderr,
                    )
                    time.sleep(random.uniform(3.0, 6.0))
                    # If redirected to verify page, try going back to homepage first
                    current_url = (getattr(page, "url", "") or "").lower()
                    if "verify" in current_url or "punish" in current_url:
                        try:
                            page.goto("https://shopee.vn/", wait_until="domcontentloaded", timeout=30000)
                            time.sleep(random.uniform(2.0, 4.0))
                        except Exception:
                            pass
                else:
                    raise

        if not nav_success:
            print("Failed to navigate to target URL after retries.", file=sys.stderr)
            errors.append(f"Navigation failed: could not reach {start_url}")

        time.sleep(random.uniform(args.min_delay, args.max_delay))

        for scroll_index in range(args.scrolls):
            if looks_like_captcha(page):
                message = "Captcha/verification detected."
                artifacts = capture_captcha_artifacts(
                    page,
                    captcha_artifact_dir,
                    platform,
                    args.captcha_screenshot_timeout_ms,
                )
                artifact_note = (
                    f"metadata: {artifacts['metadata']}; "
                    f"screenshot: {artifacts['screenshot'] or artifacts['screenshot_error']}"
                )

                errors.append(f"{message} Current URL: {page.url}; {artifact_note}")
                if args.pause_on_captcha:
                    print(
                        message
                        + " Solve manually, then press Enter. "
                        + artifact_note,
                        file=sys.stderr,
                    )
                    input()
                else:
                    print(
                        message
                        + " Rerun with --headed --pause-on-captcha. "
                        + artifact_note,
                        file=sys.stderr,
                    )
                    break

            try:
                page.mouse.move(random.randint(200, 1000), random.randint(180, 760))
                page.mouse.wheel(0, random.randint(args.min_wheel, args.max_wheel))
                time.sleep(random.uniform(args.min_delay, args.max_delay))

                if args.reload_every and (scroll_index + 1) % args.reload_every == 0:
                    page.reload(wait_until="domcontentloaded", timeout=args.timeout_ms)
                    time.sleep(random.uniform(args.min_delay, args.max_delay))
            except Exception as scroll_err:
                err_str = str(scroll_err).lower()
                if "closed" in err_str or "target" in err_str:
                    print(f"Browser/page closed during scroll: {scroll_err}", file=sys.stderr)
                    break
                raise

        if platform == "shopee":
            try:
                dom_cards = extract_shopee_dom_cards(page)
                html_card_count = len(dom_cards)
                source = f"shopee html: {page.url}"
                products.extend(normalize_shopee_dom_cards(dom_cards, source))
            except Exception as error:
                errors.append(f"Shopee HTML fallback failed: {error}")

        final_url = page.url
        if looks_like_captcha(page) and not any("Captcha/verification detected" in error for error in errors):
            artifacts = capture_captcha_artifacts(
                page,
                captcha_artifact_dir,
                platform,
                args.captcha_screenshot_timeout_ms,
            )
            artifact_note = (
                f"metadata: {artifacts['metadata']}; "
                f"screenshot: {artifacts['screenshot'] or artifacts['screenshot_error']}"
            )
            errors.append(f"Captcha/verification detected. Current URL: {final_url}; {artifact_note}")
        context.close()

    normalized = dedupe_products(products)
    normalized.sort(key=lambda item: int(item.get("sold_count") or 0), reverse=True)
    write_json(output_file, normalized)

    success = len(normalized) > 0
    print(
        json.dumps(
            {
                "success": success,
                "platform": platform,
                "start_url": start_url,
                "final_url": final_url,
                "captured_responses": captured_count,
                "html_cards": html_card_count,
                "count": len(normalized),
                "output_file": str(output_file),
                "raw_output": str(raw_file) if raw_file else "",
                "errors": errors,
            },
            ensure_ascii=False,
            indent=2,
        )
    )
    return 0 if success else 1


def parse_args(argv: list[str]) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Slow Playwright marketplace scraper.")
    parser.add_argument("--platform", choices=["shopee", "lazada"], required=True)
    parser.add_argument("--keyword", default="laptop")
    parser.add_argument("--url", default="")
    parser.add_argument("--shopee-category-id", type=int, default=0)
    parser.add_argument("--shopee-category-name", default="")
    parser.add_argument("--category-page", type=int, default=0)
    parser.add_argument("--output", default=str(BASE_PATH / "storage/data/sources/playwright_products.json"))
    parser.add_argument("--raw-output", default="")
    parser.add_argument(
        "--user-data-dir",
        default=os.environ.get("SHOPEE_PROFILE_DIR", str(BASE_PATH / "storage/browser/playwright-profile")),
    )
    parser.add_argument("--captcha-artifact-dir", default=str(BASE_PATH / "storage/data/captcha"))
    parser.add_argument("--captcha-screenshot-timeout-ms", type=int, default=5000)
    parser.add_argument("--env-file", default=str(BASE_PATH / ".env"))
    parser.add_argument("--scrolls", type=int, default=10)
    parser.add_argument("--min-delay", type=float, default=4.0)
    parser.add_argument("--max-delay", type=float, default=9.0)
    parser.add_argument("--min-wheel", type=int, default=500)
    parser.add_argument("--max-wheel", type=int, default=900)
    parser.add_argument("--reload-every", type=int, default=0)
    parser.add_argument("--timeout-ms", type=int, default=60000)
    parser.add_argument("--browser-channel", default=os.environ.get("PLAYWRIGHT_CHANNEL", "chrome"))
    parser.add_argument("--user-agent", default=os.environ.get("SCRAPER_USER_AGENT", DEFAULT_USER_AGENT))
    parser.add_argument("--headed", action="store_true")
    parser.add_argument("--shopee-login-assist", action="store_true")
    parser.add_argument("--pause-on-captcha", action="store_true")
    parser.add_argument("--warmup-session", action="store_true")
    parser.add_argument("--warmup-url", default="")
    parser.add_argument("--playwright-pause", action="store_true")
    args = parser.parse_args(argv)
    args.headless = not args.headed
    if args.min_delay > args.max_delay:
        parser.error("--min-delay must be <= --max-delay")
    return args


DEFAULT_USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/136.0.0.0 Safari/537.36"
)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv or sys.argv[1:])
    return run_browser(args)


if __name__ == "__main__":
    raise SystemExit(main())
