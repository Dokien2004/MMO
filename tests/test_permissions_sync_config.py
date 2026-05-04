import pathlib
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[1]


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


class PermissionSyncConfigTest(unittest.TestCase):
    def test_permissions_config_exists_with_admin_permissions(self):
        config = read("backend/app/config/permissions_list.php")

        self.assertIn("'admin.permissions' => 'Phân quyền theo role'", config)
        self.assertIn("'admin.sites' => 'Quản lý Sites hoặc Chi nhánh'", config)
        self.assertIn("'products.sync' => 'Đồng bộ sản phẩm'", config)

    def test_permission_service_supports_sync_from_config(self):
        service = read("backend/app/services/PermissionService.php")

        self.assertIn("function syncPermissionsFromConfig", service)
        self.assertIn("normalizeConfiguredPermissions", service)
        self.assertIn("DELETE FROM role_permissions WHERE permission_id IN", service)
        self.assertIn("DELETE FROM permissions WHERE id IN", service)

    def test_routes_and_views_expose_sync_action(self):
        index = read("backend/public/index.php")
        roles_view = read("backend/app/views/admin/roles/index.php")
        permission_view = read("backend/app/views/admin/roles/permissions.php")

        self.assertIn("'/admin/roles/sync'       => 'admin.permissions'", index)
        self.assertIn("case '/admin/roles/sync':", index)
        self.assertIn("action=\"<?= url('/admin') ?>/roles/sync\"", roles_view)
        self.assertIn("id=\"syncPermissionConfigForm\"", permission_view)


if __name__ == "__main__":
    unittest.main()
