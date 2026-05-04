import pathlib
import re
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


class FrontendAssetExtractionTest(unittest.TestCase):
    def test_login_uses_external_stylesheet(self):
        login = read("backend/app/views/auth/login.php")

        self.assertNotIn("<style", login.lower())
        self.assertIn("asset('/css/login.css')", login)

    def test_main_layout_uses_meta_csrf_not_inline_script(self):
        layout = read("backend/app/views/layouts/main.php")

        self.assertIn('name="csrf-token"', layout)
        self.assertNotIn("const CSRF_TOKEN", layout)

    def test_admin_views_do_not_embed_style_or_script_blocks(self):
        for view in (
            "backend/app/views/admin/modules.php",
            "backend/app/views/admin/permissions.php",
            "backend/app/views/admin/users.php",
        ):
            contents = read(view).lower()

            self.assertNotIn("<style", contents, view)
            self.assertNotRegex(contents, r"<script(?![^>]+src=)", view)

    def test_admin_views_load_dedicated_assets(self):
        expected_assets = {
            "backend/app/views/admin/modules.php": "js/admin/modules.js",
            "backend/app/views/admin/permissions.php": "js/admin/permissions.js",
            "backend/app/views/admin/users.php": "js/admin/users.js",
        }

        for view, script in expected_assets.items():
            contents = read(view)

            self.assertIn("css/admin.css", contents, view)
            self.assertIn(script, contents, view)

    def test_admin_views_do_not_use_inline_event_handlers(self):
        for view in (
            "backend/app/views/admin/modules.php",
            "backend/app/views/admin/permissions.php",
            "backend/app/views/admin/users.php",
        ):
            contents = read(view)

            self.assertIsNone(re.search(r"\son[a-z]+\s*=", contents), view)


if __name__ == "__main__":
    unittest.main()
