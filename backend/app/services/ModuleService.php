<?php

declare(strict_types=1);

/**
 * ModuleService — Toggle modules on/off, query enabled modules.
 * Simplified from factory-erp ModuleService (no per-site, no features).
 */
class ModuleService
{
    private PDO $pdo;
    private const CORE_MODULE_CODES = ['DASHBOARD', 'ADMIN'];

    public function __construct()
    {
        $this->pdo = db_pdo();
    }

    /**
     * Get all modules (for admin UI).
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM system_modules ORDER BY sort_order ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Get only enabled module codes (for session loading).
     * @return string[]
     */
    public function getEnabledCodes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT code FROM system_modules WHERE is_enabled = 1 ORDER BY sort_order ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Toggle a module on/off.
     */
    public function toggle(int $moduleId, bool $enabled): bool
    {
        $stmt = $this->pdo->prepare("SELECT code FROM system_modules WHERE id = :id");
        $stmt->execute([':id' => $moduleId]);
        $moduleCode = (string)$stmt->fetchColumn();

        if ($moduleCode === '') {
            throw new \InvalidArgumentException('Module không tồn tại.');
        }

        if (in_array($moduleCode, self::CORE_MODULE_CODES, true) && !$enabled) {
            throw new \InvalidArgumentException('Không thể tắt module lõi.');
        }

        $stmt = $this->pdo->prepare(
            "UPDATE system_modules SET is_enabled = :en WHERE id = :id"
        );
        $stmt->execute([':en' => $enabled ? 1 : 0, ':id' => $moduleId]);

        // Reload session
        $_SESSION['enabled_modules'] = $this->getEnabledCodes();

        return true;
    }

    /**
     * Check if a module is enabled by code.
     */
    public function isEnabled(string $code): bool
    {
        return in_array($code, $_SESSION['enabled_modules'] ?? [], true);
    }
}
