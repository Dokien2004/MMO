<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';

final class TaskLogService
{
    private DatabaseStorage $storage;
    private PDO $pdo;
    private string $fileName = 'task_logs.json';

    public function __construct()
    {
        $this->pdo = db_pdo();
        $this->storage = new DatabaseStorage();
    }

    public function create(string $taskName, string $status, array $payload = [], array $resultPayload = [], string $errorMessage = ''): array
    {
        return $this->storage->mutate($this->fileName, function (array $logs) use ($taskName, $status, $payload, $resultPayload, $errorMessage): array {
            $entry = [
                'id' => $this->storage->nextIdForRows($logs),
                'site_id' => currentSiteId(),
                'task_name' => $taskName,
                'status' => $status,
                'payload' => $payload,
                'result_payload' => $resultPayload,
                'error_message' => $errorMessage,
                'created_at' => date('c'),
            ];

            array_unshift($logs, $entry);

            return [
                'rows' => array_slice($logs, 0, 100),
                'result' => $entry,
            ];
        });
    }

    public function recent(int $limit = 10, bool $global = false): array
    {
        $where = $global ? '' : 'WHERE site_id = :site_id';
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM affiliate_task_logs ' . $where . '
             ORDER BY id DESC
             LIMIT :lim'
        );
        if (!$global) {
            $stmt->bindValue(':site_id', currentSiteId(), PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($logs as &$log) {
            $log['payload'] = json_decode((string)($log['payload'] ?? ''), true) ?: [];
            $log['result_payload'] = json_decode((string)($log['result_payload'] ?? ''), true) ?: [];
        }
        unset($log);

        return $logs;
    }
}
