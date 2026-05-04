<?php

declare(strict_types=1);

/**
 * PermissionService — Manages permissions and role-permission matrix.
 * Pattern: Simplified from factory-erp RolesModel + RolesController.
 */
class PermissionService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    /**
     * Get all permissions grouped by module_code.
     * @return array ['MODULE_CODE' => [perm1, perm2, ...], ...]
     */
    public function getAllGroupedByModule(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.*, sm.name AS module_name
             FROM permissions p
             LEFT JOIN system_modules sm ON sm.code = p.module_code
             ORDER BY sm.sort_order ASC, p.sort_order ASC, p.code ASC"
        );
        $all = $stmt->fetchAll();

        $grouped = [];
        foreach ($all as $perm) {
            $key = $perm['module_code'] ?? 'OTHER';
            $grouped[$key][] = $perm;
        }
        return $grouped;
    }

    /**
     * Get permission IDs for a specific role.
     * @return int[] Array of permission IDs
     */
    public function getPermissionIdsForRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT permission_id FROM role_permissions WHERE role_id = :rid"
        );
        $stmt->execute([':rid' => $roleId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Get all roles (for matrix columns).
     */
    public function getAllRoles(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Build permission matrix: roles × permissions.
     * @return array ['roles' => [...], 'groups' => [...], 'matrix' => [roleId => [permId => bool]]]
     */
    public function buildMatrix(): array
    {
        $roles = $this->getAllRoles();
        $groups = $this->getAllGroupedByModule();

        // Build matrix: role_id => [perm_id => true]
        $matrix = [];
        foreach ($roles as $role) {
            $permIds = $this->getPermissionIdsForRole((int)$role['id']);
            $matrix[(int)$role['id']] = array_flip($permIds);
        }

        return [
            'roles'  => $roles,
            'groups' => $groups,
            'matrix' => $matrix,
        ];
    }

    /**
     * Save the permission matrix from POST data.
     * Pattern: ERP's updatePermissionsForRole() — DELETE + INSERT approach.
     *
     * @param array $data POST data with 'perm[role_id][]' = permission_id
     */
    public function saveMatrix(array $data): array
    {
        $roles = $this->getAllRoles();
        $updated = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($roles as $role) {
                $roleId = (int)$role['id'];
                if (($role['code'] ?? '') === 'admin') {
                    $stmt = $this->pdo->query("SELECT id FROM permissions ORDER BY id ASC");
                    $permIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                } else {
                    $permIds = $data['perm'][$roleId] ?? [];
                    $permIds = array_values(array_unique(array_map('intval', (array)$permIds)));
                    $permIds = array_values(array_filter($permIds, static fn(int $id): bool => $id > 0));
                }

                // Delete existing
                $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :rid");
                $stmt->execute([':rid' => $roleId]);

                // Insert new
                if (!empty($permIds)) {
                    $placeholders = [];
                    $values = [];
                    foreach ($permIds as $pid) {
                        $placeholders[] = "(?, ?)";
                        $values[] = $roleId;
                        $values[] = $pid;
                    }
                    $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $placeholders);
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($values);
                }

                $updated++;
            }

            $this->pdo->commit();

            // Reload current user's permissions in session
            if (isset($_SESSION['role_id'])) {
                $stmt = $this->pdo->prepare(
                    "SELECT p.code FROM permissions p
                     JOIN role_permissions rp ON rp.permission_id = p.id
                     WHERE rp.role_id = :rid ORDER BY p.code"
                );
                $stmt->execute([':rid' => (int)$_SESSION['role_id']]);
                $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            return ['success' => true, 'updated' => $updated];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Save permissions for a SINGLE role.
     */
    public function saveRolePermissions(int $roleId, array $permIds): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT code FROM roles WHERE id = :id");
            $stmt->execute([':id' => $roleId]);
            $roleCode = $stmt->fetchColumn();
            
            if ($roleCode === 'admin') {
                $stmt = $this->pdo->query("SELECT id FROM permissions ORDER BY id ASC");
                $permIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } else {
                $permIds = array_values(array_unique(array_map('intval', $permIds)));
                $permIds = array_filter($permIds, fn($id) => $id > 0);
            }

            $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :rid");
            $stmt->execute([':rid' => $roleId]);

            if (!empty($permIds)) {
                $placeholders = [];
                $values = [];
                foreach ($permIds as $pid) {
                    $placeholders[] = "(?, ?)";
                    $values[] = $roleId;
                    $values[] = $pid;
                }
                $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $placeholders);
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($values);
            }

            $this->pdo->commit();

            if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === $roleId) {
                $stmt = $this->pdo->prepare(
                    "SELECT p.code FROM permissions p
                     JOIN role_permissions rp ON rp.permission_id = p.id
                     WHERE rp.role_id = :rid ORDER BY p.code"
                );
                $stmt->execute([':rid' => $roleId]);
                $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
