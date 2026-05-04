import pathlib
import re
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


class AdminSiteManagementTest(unittest.TestCase):
    def test_site_migration_defines_sites_and_permission(self):
        migration = read("backend/database/migrations/003_sites_system.sql")

        self.assertIn("CREATE TABLE IF NOT EXISTS `sites`", migration)
        self.assertIn("`parent_site_id`", migration)
        self.assertIn("`is_master`", migration)
        self.assertIn("'admin.sites'", migration)

    def test_site_service_exists_with_core_operations(self):
        service = read("backend/app/services/SiteService.php")

        self.assertIn("class SiteService", service)
        self.assertIn("function getAll", service)
        self.assertIn("function create", service)
        self.assertIn("function update", service)
        self.assertIn("function toggleActive", service)
        self.assertIn("function changeCurrentSite", service)

    def test_site_service_schema_checks_are_mariadb_safe(self):
        service = read("backend/app/services/SiteService.php")

        self.assertNotIn("SHOW COLUMNS FROM `{$table}` LIKE :column", service)
        self.assertNotIn("SHOW TABLES LIKE :table", service)
        self.assertIn("information_schema.COLUMNS", service)
        self.assertIn("information_schema.TABLES", service)

    def test_admin_sites_route_and_permissions_are_registered(self):
        index = read("backend/public/index.php")

        self.assertIn("'/admin/sites'   => 'ADMIN'", index)
        self.assertIn("'/admin/sites/store'     => 'admin.sites'", index)
        self.assertIn("case '/admin/sites':", index)
        self.assertIn("'currentPage'  => 'admin_sites'", index)

    def test_admin_site_view_uses_external_assets(self):
        view = read("backend/app/views/admin/sites.php")

        self.assertIn("css/admin.css", view)
        self.assertIn("js/admin/sites.js", view)
        self.assertNotIn("<style", view.lower())
        self.assertNotRegex(view, r"<script(?![^>]+src=)")
        self.assertIsNone(re.search(r"\son[a-z]+\s*=", view))

    def test_admin_tabs_include_sites(self):
        for view in (
            "backend/app/views/admin/modules.php",
            "backend/app/views/admin/permissions.php",
            "backend/app/views/admin/users.php",
            "backend/app/views/admin/sites.php",
        ):
            contents = read(view)

            self.assertIn("admin/sites", contents, view)
            self.assertIn("Sites", contents, view)


if __name__ == "__main__":
    unittest.main()
