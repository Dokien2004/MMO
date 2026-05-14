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
        $override = trim((string)getenv('PENDING_SCRAPE_JOBS_FILE'));
        $this->file = $override !== '' ? $override : (STORAGE_PATH . '/data/pending_scrape_jobs.json');
        if (!is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }
    }

    public function create(string $type, array $payload, string $status = 'waiting'): array
    {
        return $this->withLockedJobs(function (array $jobs) use ($type, $payload, $status): array {
            $job = [
                'id' => 'scrape_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)),
                'type' => $type,
                'payload' => $payload,
                'status' => $status,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];
            $jobs[] = $job;

            return [
                'jobs' => $jobs,
                'result' => $job,
            ];
        });
    }

    public function latestWaiting(): ?array
    {
        return $this->findLatestByStatuses(['waiting']);
    }

    public function latestRunnable(): ?array
    {
        return $this->findLatestByStatuses(['queued', 'waiting']);
    }

    public function mark(string $id, string $status, array $extra = []): void
    {
        $this->withLockedJobs(function (array $jobs) use ($id, $status, $extra): array {
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

            return [
                'jobs' => $jobs,
                'result' => null,
            ];
        });
    }

    public function all(): array
    {
        return $this->withLockedJobs(static fn(array $jobs): array => [
            'jobs' => $jobs,
            'result' => $jobs,
        ], LOCK_SH);
    }

    private function saveAll(array $jobs): void
    {
        $this->withLockedJobs(static fn() => [
            'jobs' => array_values($jobs),
            'result' => null,
        ]);
    }

    private function findLatestByStatuses(array $statuses): ?array
    {
        return $this->withLockedJobs(static function (array $jobs) use ($statuses): array {
            $match = null;
            foreach (array_reverse($jobs) as $job) {
                if (in_array(($job['status'] ?? ''), $statuses, true)) {
                    $match = $job;
                    break;
                }
            }

            return [
                'jobs' => $jobs,
                'result' => $match,
            ];
        }, LOCK_SH);
    }

    private function withLockedJobs(callable $callback, int $lockMode = LOCK_EX): mixed
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Khong the mo pending scrape jobs file.');
        }

        if (!flock($handle, $lockMode)) {
            fclose($handle);
            throw new RuntimeException('Khong the lock pending scrape jobs file.');
        }

        try {
            $jobs = $this->readFromHandle($handle);
            $mutation = $callback($jobs);
            $nextJobs = is_array($mutation['jobs'] ?? null) ? array_values($mutation['jobs']) : $jobs;

            if ($lockMode === LOCK_EX) {
                $this->writeToHandle($handle, $nextJobs);
            }

            return $mutation['result'] ?? null;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function readFromHandle($handle): array
    {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $data = json_decode((string)$raw, true);

        return is_array($data) ? $data : [];
    }

    private function writeToHandle($handle, array $jobs): void
    {
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($handle);
    }
}
