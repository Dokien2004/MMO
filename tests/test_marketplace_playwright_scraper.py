import importlib.util
import pathlib
import tempfile
import unittest


MODULE_PATH = pathlib.Path(__file__).resolve().parents[1] / "scripts" / "marketplace_playwright_scraper.py"
SPEC = importlib.util.spec_from_file_location("marketplace_playwright_scraper", MODULE_PATH)
scraper = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(scraper)


class MarketplacePlaywrightScraperTest(unittest.TestCase):
    def test_should_capture_known_marketplace_search_responses(self):
        self.assertTrue(scraper.should_capture_url("shopee", "https://shopee.vn/api/v4/search/search_items?keyword=laptop"))
        self.assertTrue(scraper.should_capture_url("lazada", "https://www.lazada.vn/catalog/?ajax=true&q=laptop"))
        self.assertTrue(
            scraper.should_capture_url(
                "lazada",
                "https://acs-m.lazada.vn/h5/mtop.lazada.search.mtopsearch/1.0/?data=abc",
            )
        )
        self.assertFalse(scraper.should_capture_url("shopee", "https://shopee.vn/api/v4/account/basic/get"))
        self.assertFalse(
            scraper.should_capture_url(
                "lazada",
                "https://acs-m.lazada.vn/h5/mtop.lazada.carts.ultron.query.cutover/1.0/",
            )
        )

    def test_normalizes_shopee_search_payload(self):
        payload = {
            "items": [
                {
                    "item_basic": {
                        "shopid": 111,
                        "itemid": 222,
                        "name": "Laptop test",
                        "price": 17900000000,
                        "sold": 123,
                    }
                }
            ]
        }

        products = scraper.normalize_payload("shopee", payload, "Shopee search")

        self.assertEqual(
            products,
            [
                {
                    "source_product_id": "SH-111-222",
                    "product_name": "Laptop test",
                    "product_url": "https://shopee.vn/product/111/222",
                    "price": 179000.0,
                    "sold_count": 123,
                    "notes": "Shopee search",
                }
            ],
        )

    def test_normalizes_lazada_catalog_payload(self):
        payload = {
            "mods": {
                "listItems": [
                    {
                        "itemId": "abc123",
                        "name": "Mouse test",
                        "productUrl": "//www.lazada.vn/products/mouse-test-i123.html",
                        "price": "1.790.000",
                        "itemSoldCntShow": "Da ban 1,2K",
                    }
                ]
            }
        }

        products = scraper.normalize_payload("lazada", payload, "Lazada catalog")

        self.assertEqual(products[0]["source_product_id"], "LZ-abc123")
        self.assertEqual(products[0]["product_url"], "https://www.lazada.vn/products/mouse-test-i123.html")
        self.assertEqual(products[0]["price"], 1790000.0)
        self.assertEqual(products[0]["sold_count"], 1200)

    def test_normalizes_lazada_mtop_search_payload(self):
        payload = {
            "data": {
                "root": {
                    "fields": {
                        "listItems": [
                            {
                                "nid": "98765",
                                "name": "Laptop Lazada test",
                                "productUrl": "https://www.lazada.vn/products/laptop-test-i98765.html",
                                "price": "12,490,000",
                                "sold": "2.3K",
                            }
                        ]
                    }
                }
            }
        }

        products = scraper.normalize_payload("lazada", payload, "Lazada mtop")

        self.assertEqual(products[0]["source_product_id"], "LZ-98765")
        self.assertEqual(products[0]["product_name"], "Laptop Lazada test")
        self.assertEqual(products[0]["price"], 12490000.0)
        self.assertEqual(products[0]["sold_count"], 2300)

    def test_builds_shopee_category_sales_url(self):
        self.assertEqual(
            scraper.build_shopee_category_url(11035567, 0, "Thời Trang Nam"),
            "https://shopee.vn/Th%E1%BB%9Di-Trang-Nam-cat.11035567?page=0&sortBy=sales",
        )

    def test_normalizes_shopee_dom_product_cards(self):
        cards = [
            {
                "href": "https://shopee.vn/Ao-thun-nam-i.12345.67890?sp_atk=test",
                "text": "Yêu thích\nÁo thun nam cotton form rộng\n₫79.000\nĐã bán 1,2k",
                "imageAlt": "Áo thun nam cotton form rộng",
            }
        ]

        products = scraper.normalize_shopee_dom_cards(cards, "Shopee [Thời Trang Nam]")

        self.assertEqual(
            products,
            [
                {
                    "source_product_id": "SH-12345-67890",
                    "product_name": "Áo thun nam cotton form rộng",
                    "product_url": "https://shopee.vn/product/12345/67890",
                    "price": 79000.0,
                    "sold_count": 1200,
                    "notes": "Shopee [Thời Trang Nam]",
                }
            ],
        )

    def test_headed_linux_requires_display(self):
        self.assertTrue(scraper.headed_linux_without_display(True, "linux", {}))
        self.assertFalse(scraper.headed_linux_without_display(True, "linux", {"DISPLAY": ":99"}))
        self.assertFalse(scraper.headed_linux_without_display(False, "linux", {}))
        self.assertFalse(scraper.headed_linux_without_display(True, "darwin", {}))

    def test_detects_shopee_verify_traffic_page(self):
        class FakeLocator:
            def inner_text(self, timeout):
                return "Xác nhận để tiếp tục Kéo qua để hoàn thiện bức hình"

        class FakePage:
            url = "https://shopee.vn/verify/traffic/error"

            def locator(self, selector):
                return FakeLocator()

        self.assertTrue(scraper.looks_like_captcha(FakePage()))

    def test_builds_captcha_artifact_paths(self):
        paths = scraper.build_captcha_artifact_paths(pathlib.Path("/tmp/captcha"), "Shopee VN", 1777823000)

        self.assertEqual(paths["screenshot"], pathlib.Path("/tmp/captcha/shopee-vn_captcha_1777823000.png"))
        self.assertEqual(paths["metadata"], pathlib.Path("/tmp/captcha/shopee-vn_captcha_1777823000.json"))

    def test_browser_channel_attempts_fall_back_to_bundled_chromium(self):
        self.assertEqual(scraper.build_browser_channel_attempts("chrome"), ["chrome", ""])
        self.assertEqual(scraper.build_browser_channel_attempts("bundled"), [""])
        self.assertEqual(scraper.build_browser_channel_attempts("chromium"), [""])

    def test_finds_chrome_profile_lock_files(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            profile = pathlib.Path(temp_dir)
            (profile / "Default").mkdir()
            (profile / "SingletonLock").write_text("", encoding="utf-8")
            (profile / "Default" / "LOCK").write_text("", encoding="utf-8")

            self.assertEqual(
                scraper.find_profile_lock_files(profile),
                [profile / "SingletonLock", profile / "Default" / "LOCK"],
            )

    def test_parses_env_file_credentials_without_leaking_defaults(self):
        text = """
        # comment
        SHOPEE_USERNAME="user@example.com"
        SHOPEE_PASSWORD='secret value'
        EMPTY_VALUE=
        """

        parsed = scraper.parse_env_text(text)

        self.assertEqual(parsed["SHOPEE_USERNAME"], "user@example.com")
        self.assertEqual(parsed["SHOPEE_PASSWORD"], "secret value")
        self.assertEqual(parsed["EMPTY_VALUE"], "")

    def test_loads_shopee_credentials_from_environment_first(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            env_file = pathlib.Path(temp_dir) / ".env"
            env_file.write_text("SHOPEE_USERNAME=file-user\nSHOPEE_PASSWORD=file-pass\n", encoding="utf-8")

            credentials = scraper.load_shopee_credentials(
                {"SHOPEE_USERNAME": "env-user", "SHOPEE_PASSWORD": "env-pass"},
                env_file,
            )

        self.assertEqual(credentials, ("env-user", "env-pass"))

    def test_shopee_login_assist_fills_credentials_and_submits(self):
        class FakeLocator:
            def __init__(self, page, selector):
                self.page = page
                self.selector = selector

            def is_visible(self, timeout):
                return self.selector in self.page.visible_selectors

            def fill(self, value, timeout):
                self.page.actions.append(("fill", self.selector, value))

            def click(self, timeout):
                self.page.actions.append(("click", self.selector))

        class FakePage:
            def __init__(self):
                self.actions = []
                self.visible_selectors = {
                    'input[autocomplete="username"]',
                    'input[type="password"]',
                    'button[type="submit"]',
                }

            def goto(self, url, wait_until, timeout):
                self.actions.append(("goto", url, wait_until, timeout))

            def locator(self, selector):
                return FakeLocator(self, selector)

        page = FakePage()

        submitted = scraper.run_shopee_login_assist(page, "user@example.com", "secret", 1234)

        self.assertTrue(submitted)
        self.assertEqual(page.actions[0], ("goto", scraper.SHOPEE_LOGIN_URL, "domcontentloaded", 1234))
        self.assertIn(("fill", 'input[autocomplete="username"]', "user@example.com"), page.actions)
        self.assertIn(("fill", 'input[type="password"]', "secret"), page.actions)
        self.assertIn(("click", 'button[type="submit"]'), page.actions)


if __name__ == "__main__":
    unittest.main()
