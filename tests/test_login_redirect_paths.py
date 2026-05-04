import pathlib
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


class LoginRedirectPathTest(unittest.TestCase):
    def test_view_helper_normalizes_internal_paths(self):
        helper = read("backend/app/helpers/view_helper.php")

        self.assertIn("function normalize_internal_path", helper)
        self.assertIn("parse_url($path, PHP_URL_PATH)", helper)
        self.assertIn("strpos($pathOnly, $basePath) === 0", helper)

    def test_auth_helper_stores_normalized_redirect_path(self):
        helper = read("backend/app/helpers/auth_helper.php")

        self.assertIn("normalize_internal_path(", helper)
        self.assertIn("$_SERVER['REQUEST_URI'] ?? '/'", helper)
        self.assertNotIn("$_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';", helper)


if __name__ == "__main__":
    unittest.main()
