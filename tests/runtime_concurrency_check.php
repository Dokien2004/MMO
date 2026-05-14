#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/app/bootstrap.php';

const TEST_SITE_ID = 99991;

$options = getopt('', [
    'mode::',
    'target::',
    'post-id::',
    'job-id::',
    'status::',
    'note::',
    'start-at::',
    'pending-file::',
]);

$mode = (string)($options['mode'] ?? 'parent');

if ($mode === 'worker') {
    runWorker($options);
    exit(0);
}

runParent();

function runParent(): void
{
    $_SESSION['site_id'] = TEST_SITE_ID;

    new DatabaseStorage();
    $pdo = db_pdo();
    $pendingFile = sys_get_temp_dir() . '/affiliate_pending_jobs_' . getmypid() . '.json';
    @unlink($pendingFile);

    try {
        cleanupSiteData($pdo, TEST_SITE_ID);
        seedScheduledPosts($pdo, TEST_SITE_ID);
        $pendingJobIds = seedPendingJobs($pendingFile);

        $scheduledResult = runScheduledPostsRace();
        $pendingResult = runPendingJobsRace($pendingFile, $pendingJobIds);

        $payload = [
            'ok' => $scheduledResult['ok'] && $pendingResult['ok'],
            'scheduled_posts' => $scheduledResult,
            'pending_scrape_jobs' => $pendingResult,
        ];

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit($payload['ok'] ? 0 : 1);
    } finally {
        cleanupSiteData($pdo, TEST_SITE_ID);
        @unlink($pendingFile);
    }
}

function runWorker(array $options): void
{
    $_SESSION['site_id'] = TEST_SITE_ID;

    $target = (string)($options['target'] ?? '');
    $startAt = (float)($options['start-at'] ?? 0);
    waitUntil($startAt);

    if ($target === 'scheduled_post') {
        $service = new PostingService();
        $postId = (int)($options['post-id'] ?? 0);
        $status = (string)($options['status'] ?? 'failed');
        $note = (string)($options['note'] ?? 'worker note');
        if ($status === 'success') {
            $service->markPosted($postId, $note);
        } else {
            $service->markFailed($postId, $note);
        }
        return;
    }

    if ($target === 'pending_job') {
        $pendingFile = (string)($options['pending-file'] ?? '');
        if ($pendingFile !== '') {
            putenv('PENDING_SCRAPE_JOBS_FILE=' . $pendingFile);
        }
        $service = new PendingScrapeJobService();
        $service->mark((string)($options['job-id'] ?? ''), (string)($options['status'] ?? 'done'), [
            'worker_note' => (string)($options['note'] ?? ''),
        ]);
        return;
    }

    throw new InvalidArgumentException('Unknown worker target.');
}

function runScheduledPostsRace(): array
{
    $startAt = microtime(true) + 1.5;
    $script = __FILE__;

    $commands = [
        buildPhpCommand([
            $script,
            '--mode=worker',
            '--target=scheduled_post',
            '--post-id=7001',
            '--status=failed',
            '--note=worker-a',
            '--start-at=' . sprintf('%.6f', $startAt),
        ]),
        buildPhpCommand([
            $script,
            '--mode=worker',
            '--target=scheduled_post',
            '--post-id=7002',
            '--status=failed',
            '--note=worker-b',
            '--start-at=' . sprintf('%.6f', $startAt),
        ]),
    ];

    runConcurrentCommands($commands);

    $pdo = db_pdo();
    $stmt = $pdo->prepare('SELECT id, status, result_note FROM scheduled_posts WHERE site_id = :site_id AND id IN (7001, 7002) ORDER BY id ASC');
    $stmt->execute([':site_id' => TEST_SITE_ID]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ok = count($rows) === 2
        && ($rows[0]['status'] ?? '') === 'failed'
        && ($rows[0]['result_note'] ?? '') === 'worker-a'
        && ($rows[1]['status'] ?? '') === 'failed'
        && ($rows[1]['result_note'] ?? '') === 'worker-b';

    return [
        'ok' => $ok,
        'rows' => $rows,
    ];
}

function runPendingJobsRace(string $pendingFile, array $jobIds): array
{
    $startAt = microtime(true) + 1.5;
    $script = __FILE__;

    $commands = [
        buildPhpCommand([
            $script,
            '--mode=worker',
            '--target=pending_job',
            '--job-id=' . $jobIds[0],
            '--status=done',
            '--note=worker-a',
            '--pending-file=' . $pendingFile,
            '--start-at=' . sprintf('%.6f', $startAt),
        ]),
        buildPhpCommand([
            $script,
            '--mode=worker',
            '--target=pending_job',
            '--job-id=' . $jobIds[1],
            '--status=failed',
            '--note=worker-b',
            '--pending-file=' . $pendingFile,
            '--start-at=' . sprintf('%.6f', $startAt),
        ]),
    ];

    runConcurrentCommands($commands);

    $service = new PendingScrapeJobServiceForFile($pendingFile);
    $jobs = $service->all();

    $indexed = [];
    foreach ($jobs as $job) {
        $indexed[(string)($job['id'] ?? '')] = $job;
    }

    $ok = isset($indexed[$jobIds[0]], $indexed[$jobIds[1]])
        && ($indexed[$jobIds[0]]['status'] ?? '') === 'done'
        && ($indexed[$jobIds[0]]['worker_note'] ?? '') === 'worker-a'
        && ($indexed[$jobIds[1]]['status'] ?? '') === 'failed'
        && ($indexed[$jobIds[1]]['worker_note'] ?? '') === 'worker-b';

    return [
        'ok' => $ok,
        'jobs' => array_values($indexed),
    ];
}

function cleanupSiteData(PDO $pdo, int $siteId): void
{
    foreach ([
        'affiliate_task_logs',
        'scheduled_posts',
        'generated_contents',
        'affiliate_links',
        'affiliate_products',
    ] as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE site_id = :site_id");
        $stmt->execute([':site_id' => $siteId]);
    }
}

function seedScheduledPosts(PDO $pdo, int $siteId): void
{
    $now = date('Y-m-d H:i:s');

    $productStmt = $pdo->prepare(
        'INSERT INTO affiliate_products
            (id, site_id, source_platform, source_product_id, product_name, product_url, price, sold_count, status, notes, affiliate_url, content_status, created_at, updated_at)
         VALUES
            (:id, :site_id, :platform, :source_product_id, :product_name, :product_url, :price, :sold_count, :status, :notes, :affiliate_url, :content_status, :created_at, :updated_at)'
    );

    $contentStmt = $pdo->prepare(
        'INSERT INTO generated_contents
            (id, site_id, product_id, affiliate_link_id, title, body, hashtags, call_to_action, ai_provider, media_type, media_url, media_prompt, media_status, status, notes, created_at, updated_at)
         VALUES
            (:id, :site_id, :product_id, NULL, :title, :body, :hashtags, :call_to_action, :ai_provider, :media_type, :media_url, :media_prompt, :media_status, :status, :notes, :created_at, :updated_at)'
    );

    $postStmt = $pdo->prepare(
        'INSERT INTO scheduled_posts
            (id, site_id, content_id, product_id, channel, social_channel_id, scheduled_at, posted_at, status, result_note, remote_post_id, created_at, updated_at)
         VALUES
            (:id, :site_id, :content_id, :product_id, :channel, NULL, :scheduled_at, NULL, :status, :result_note, :remote_post_id, :created_at, :updated_at)'
    );

    foreach ([1, 2] as $index) {
        $productId = 5000 + $index;
        $contentId = 6000 + $index;
        $postId = 7000 + $index;

        $productStmt->execute([
            ':id' => $productId,
            ':site_id' => $siteId,
            ':platform' => 'shopee',
            ':source_product_id' => 'test-' . $productId,
            ':product_name' => 'Runtime Test Product ' . $index,
            ':product_url' => 'https://example.com/product-' . $productId,
            ':price' => 100000,
            ':sold_count' => 10,
            ':status' => 'posted',
            ':notes' => 'runtime test',
            ':affiliate_url' => 'https://example.com/aff-' . $productId,
            ':content_status' => 'used',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $contentStmt->execute([
            ':id' => $contentId,
            ':site_id' => $siteId,
            ':product_id' => $productId,
            ':title' => 'Runtime Test Content ' . $index,
            ':body' => 'body',
            ':hashtags' => '#test',
            ':call_to_action' => 'cta',
            ':ai_provider' => 'template_engine',
            ':media_type' => 'none',
            ':media_url' => '',
            ':media_prompt' => '',
            ':media_status' => 'none',
            ':status' => 'approved',
            ':notes' => 'runtime test',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $postStmt->execute([
            ':id' => $postId,
            ':site_id' => $siteId,
            ':content_id' => $contentId,
            ':product_id' => $productId,
            ':channel' => 'fanpage_manual',
            ':scheduled_at' => $now,
            ':status' => 'scheduled',
            ':result_note' => '',
            ':remote_post_id' => '',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

function seedPendingJobs(string $pendingFile): array
{
    putenv('PENDING_SCRAPE_JOBS_FILE=' . $pendingFile);
    $service = new PendingScrapeJobService();
    $jobA = $service->create('config', ['config_id' => 101], 'queued');
    $jobB = $service->create('trending', ['platform' => 'shopee'], 'waiting');

    return [(string)$jobA['id'], (string)$jobB['id']];
}

function runConcurrentCommands(array $commands): void
{
    $processes = [];
    foreach ($commands as $command) {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot spawn worker process.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes, $command];
    }

    foreach ($processes as [$process, $pipes, $command]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException("Worker failed: {$command}\nSTDOUT: {$stdout}\nSTDERR: {$stderr}");
        }
    }
}

function buildPhpCommand(array $arguments): string
{
    $parts = [escapeshellarg(PHP_BINARY)];
    foreach ($arguments as $argument) {
        $parts[] = escapeshellarg($argument);
    }

    return implode(' ', $parts);
}

function waitUntil(float $startAt): void
{
    while (microtime(true) < $startAt) {
        usleep(10_000);
    }
}

final class PendingScrapeJobServiceForFile
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $data = json_decode((string)file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }
}
