<?php

declare(strict_types=1);

/**
 * PermissionService — Manages permissions and role-permission matrix.
 * Pattern: Simplified from factory-erp RolesModel + RolesController.
 */
class PermissionService
{
    private PDO $pdo;
    private const MODULE_MAP = [
        'scraper' => 'SCRAPER',
        'products' => 'PRODUCTS',
        'links' => 'LINKS',
        'contents' => 'CONTENTS',
        'posts' => 'POSTS',
        'settings' => 'SETTINGS',
        'logs' => 'LOGS',
        'admin' => 'ADMIN',
    ];

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

    /**
     * Sync configured permissions into database.
     * Inserts new permissions, updates renamed ones, and removes stale entries.
     */
    public function syncPermissionsFromConfig(array $permissionsList): array
    {
        $normalizedPermissions = $this->normalizeConfiguredPermissions($permissionsList);

        $this->pdo->beginTransaction();
        try {
            $inserted = 0;
            $updated = 0;
            $removed = 0;

            $stmt = $this->pdo->query("SELECT id, code, name, module_code, sort_order FROM permissions ORDER BY id ASC");
            $existingRows = $stmt->fetchAll();
            $existingByCode = [];
            foreach ($existingRows as $row) {
                $existingByCode[$row['code']] = $row;
            }

            foreach ($normalizedPermissions as $permission) {
                $existing = $existingByCode[$permission['code']] ?? null;

                if ($existing === null) {
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO permissions (code, name, module_code, sort_order)
                         VALUES (:code, :name, :module_code, :sort_order)"
                    );
                    $stmt->execute([
                        ':code' => $permission['code'],
                        ':name' => $permission['name'],
                        ':module_code' => $permission['module_code'],
                        ':sort_order' => $permission['sort_order'],
                    ]);
                    $inserted++;
                    continue;
                }

                $needsUpdate = $existing['name'] !== $permission['name']
                    || (string)($existing['module_code'] ?? '') !== (string)$permission['module_code']
                    || (int)($existing['sort_order'] ?? 99) !== $permission['sort_order'];

                if ($needsUpdate) {
                    $stmt = $this->pdo->prepare(
                        "UPDATE permissions
                         SET name = :name, module_code = :module_code, sort_order = :sort_order
                         WHERE id = :id"
                    );
                    $stmt->execute([
                        ':name' => $permission['name'],
                        ':module_code' => $permission['module_code'],
                        ':sort_order' => $permission['sort_order'],
                        ':id' => (int)$existing['id'],
                    ]);
                    $updated++;
                }
            }

            $validCodes = array_column($normalizedPermissions, 'code');
            $stalePermissions = array_values(array_filter(
                $existingRows,
                static fn(array $row): bool => !in_array($row['code'], $validCodes, true)
            ));

            if ($stalePermissions !== []) {
                $staleIds = array_map(static fn(array $row): int => (int)$row['id'], $stalePermissions);
                $deletePlaceholders = implode(', ', array_fill(0, count($staleIds), '?'));

                $stmt = $this->pdo->prepare(
                    "DELETE FROM role_permissions WHERE permission_id IN ($deletePlaceholders)"
                );
                $stmt->execute($staleIds);

                $stmt = $this->pdo->prepare(
                    "DELETE FROM permissions WHERE id IN ($deletePlaceholders)"
                );
                $stmt->execute($staleIds);

                $removed = count($staleIds);
            }

            $this->pdo->commit();
            $this->reloadCurrentSessionPermissions();

            if ($inserted === 0 && $updated === 0 && $removed === 0) {
                return [
                    'success' => true,
                    'type' => 'info',
                    'message' => 'Danh sách quyền đã đồng bộ và hiện không có thay đổi.',
                    'inserted' => 0,
                    'updated' => 0,
                    'removed' => 0,
                ];
            }

            return [
                'success' => true,
                'type' => 'success',
                'message' => "Đã đồng bộ quyền. Thêm mới: {$inserted}, cập nhật: {$updated}, xóa thừa: {$removed}.",
                'inserted' => $inserted,
                'updated' => $updated,
                'removed' => $removed,
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<int, array{code: string, name: string, module_code: ?string, sort_order: int}>
     */
    private function normalizeConfiguredPermissions(array $permissionsList): array
    {
        $normalized = [];
        $sortByModule = [];

        foreach ($permissionsList as $code => $definition) {
            if (!is_string($code) || trim($code) === '') {
                throw new InvalidArgumentException('Permission code trong file cấu hình không hợp lệ.');
            }

            $name = is_array($definition) ? (string)($definition['name'] ?? '') : (string)$definition;
            if (trim($name) === '') {
                throw new InvalidArgumentException("Permission [$code] thiếu tên hiển thị.");
            }

            $moduleCode = is_array($definition) && isset($definition['module_code'])
                ? strtoupper((string)$definition['module_code'])
                : $this->detectModuleCodeFromPermission($code);

            if (!isset($sortByModule[$moduleCode ?? 'OTHER'])) {
                $sortByModule[$moduleCode ?? 'OTHER'] = 0;
            }
            $sortByModule[$moduleCode ?? 'OTHER']++;

            $normalized[] = [
                'code' => trim($code),
                'name' => trim($name),
                'module_code' => $moduleCode,
                'sort_order' => is_array($definition) && isset($definition['sort_order'])
                    ? max(1, (int)$definition['sort_order'])
                    : $sortByModule[$moduleCode ?? 'OTHER'],
            ];
        }

        return $normalized;
    }

    private function detectModuleCodeFromPermission(string $code): ?string
    {
        $prefix = strtolower((string)strtok($code, '.'));
        return self::MODULE_MAP[$prefix] ?? null;
    }

    private function reloadCurrentSessionPermissions(): void
    {
        if (!isset($_SESSION['role_id'])) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT p.code FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :rid ORDER BY p.code"
        );
        $stmt->execute([':rid' => (int)$_SESSION['role_id']]);
        $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
