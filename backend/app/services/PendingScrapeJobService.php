<?php

declare(strict_types=1);

/**
 * PendingScrapeJobService — lưu job scraper đang chờ Kiên xử lý Shopee qua RustDesk.
 */
class PendingScrapeJobService
{
    private string $file;

    public function __construct()
    {
        $this->file = STORAGE_PATH . '/data/pending_scrape_jobs.json';
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }
    }

    public function create(string $type, array $payload, string $status = 'waiting'): array
    {
        $jobs = $this->all();
        $job = [
            'id' => 'scrape_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)),
            'type' => $type,
            'payload' => $payload,
            'status' => $status,
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $jobs[] = $job;
        $this->saveAll($jobs);
        return $job;
    }

    public function latestWaiting(): ?array
    {
        $jobs = array_reverse($this->all());
        foreach ($jobs as $job) {
            if (($job['status'] ?? '') === 'waiting') {
                return $job;
            }
        }
        return null;
    }

    public function latestRunnable(): ?array
    {
        $jobs = array_reverse($this->all());
        foreach ($jobs as $job) {
            if (in_array(($job['status'] ?? ''), ['queued', 'waiting'], true)) {
                return $job;
            }
        }
        return null;
    }

    public function mark(string $id, string $status, array $extra = []): void
    {
        $jobs = $this->all();
        foreach ($jobs as &$job) {
            if (($job['id'] ?? '') === $id) {
                $job['status'] = $status;
                $job['updated_at'] = date('c');
                foreach ($extra as $key => $value) {
                    $job[$key] = $value;
                }
                break;
            }
        }
        unset($job);
        $this->saveAll($jobs);
    }

    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $raw = file_get_contents($this->file);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    private function saveAll(array $jobs): void
    {
        file_put_contents($this->file, json_encode(array_values($jobs), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
