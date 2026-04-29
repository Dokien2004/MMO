<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseStorage.php';

final class TaskLogService
{
    private DatabaseStorage $storage;
    private string $fileName = 'task_logs.json';

    public function __construct()
    {
        $this->storage = new DatabaseStorage();
    }

    public function create(string $taskName, string $status, array $payload = [], array $resultPayload = [], string $errorMessage = ''): array
    {
        $logs = $this->storage->read($this->fileName);
        $entry = [
            'id' => count($logs) + 1,
            'site_id' => APP_SITE_ID,
            'task_name' => $taskName,
            'status' => $status,
            'payload' => $payload,
            'result_payload' => $resultPayload,
            'error_message' => $errorMessage,
            'created_at' => date('c'),
        ];

        array_unshift($logs, $entry);
        $this->storage->write($this->fileName, array_slice($logs, 0, 100));

        return $entry;
    }

    public function recent(int $limit = 10): array
    {
        $logs = $this->storage->read($this->fileName);
        foreach ($logs as &$log) {
            if (!isset($log['site_id'])) {
                $log['site_id'] = APP_SITE_ID;
            }
        }
        unset($log);

        return array_slice($logs, 0, $limit);
    }
}
