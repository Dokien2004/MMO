<?php

declare(strict_types=1);

final class VideoMediaService
{
    private ContentService $contentService;
    private ProductSyncService $productSyncService;
    private TaskLogService $taskLogService;

    public function __construct()
    {
        $this->contentService = new ContentService();
        $this->productSyncService = new ProductSyncService();
        $this->taskLogService = new TaskLogService();
    }

    public function generateForContent(int $contentId): array
    {
        $content = $this->contentService->findById($contentId);
        if ($content === null) {
            throw new InvalidArgumentException('Khong tim thay content de tao video.');
        }

        $product = $this->productSyncService->findProductById((int)($content['product_id'] ?? 0));
        $prompt = $this->buildVideoPrompt($content, $product ?? []);
        $relativeUrl = match (video_provider()) {
            'meigen' => $this->generateMeiGenVideo($contentId, $prompt, $content),
            'kling' => $this->generateKlingVideo($contentId, $prompt),
            default => $this->generateLocalPromoVideo($contentId, $content),
        };
        $updated = $this->contentService->attachVideo($contentId, $relativeUrl, $prompt, 'ready');

        $this->taskLogService->create('generate_content_video', 'success', [
            'content_id' => $contentId,
            'provider' => video_provider(),
            'model' => video_model(),
        ], [
            'media_url' => $relativeUrl,
        ]);

        return $updated;
    }

    public function generateForPendingContents(int $limit = 5, string $platform = 'general'): array
    {
        $limit = max(1, min(20, $limit));
        $contents = $this->contentService->allContents();
        $generated = [];
        $errors = [];

        foreach ($contents as $content) {
            if (count($generated) >= $limit) {
                break;
            }
            if (($content['media_type'] ?? 'none') === 'video' && !empty($content['media_url'] ?? '')) {
                continue;
            }

            $contentId = (int)($content['id'] ?? 0);
            if ($contentId <= 0) {
                continue;
            }

            try {
                $generated[] = $this->generateForContent($contentId);
            } catch (Throwable $throwable) {
                $message = $throwable->getMessage();
                $errors[] = [
                    'content_id' => $contentId,
                    'error' => $message,
                ];
                $this->taskLogService->create('/contents/generate-videos', 'failed', [
                    'content_id' => $contentId,
                    'provider' => video_provider(),
                    'model' => video_provider() === 'kling' ? kling_model() : video_model(),
                ], [], $message);

                if (stripos($message, 'Account balance not enough') !== false || stripos($message, 'không đủ số dư') !== false) {
                    break;
                }
            }
        }

        return [
            'count' => count($generated),
            'generated' => $generated,
            'errors' => $errors,
        ];
    }

    private function generateMeiGenVideo(int $contentId, string $prompt, array $content = []): string
    {
        $token = meigen_api_token();
        if ($token === '') {
            throw new RuntimeException('Chưa cấu hình MEIGEN_API_TOKEN cho site hiện tại. Vào Settings nhập MeiGen API Token trước khi tạo video AI.');
        }

        $payload = [
            'prompt' => $prompt,
            'modelId' => video_model() !== '' ? video_model() : 'seedance-2-0',
            'aspectRatio' => video_aspect_ratio() !== '' ? video_aspect_ratio() : '9:16',
            'resolution' => video_resolution() !== '' ? video_resolution() : '720p',
            'duration' => max(4, min(15, video_duration_seconds())),
        ];

        $referenceImages = $this->referenceImagesForContent($content);
        if (!empty($referenceImages)) {
            $payload['referenceImages'] = $referenceImages;
        }

        $created = $this->postMeiGenJson(meigen_base_url() . '/generate/v2', $payload, $token);
        $generationId = (string)($created['generationId'] ?? '');
        if ($generationId === '') {
            throw new RuntimeException('MeiGen API không trả về generationId khi tạo video.');
        }

        $deadline = time() + max(180, image_timeout_seconds());
        $statusUrl = meigen_base_url() . '/generate/v2/status/' . rawurlencode($generationId);
        do {
            sleep(5);
            $status = $this->getMeiGenJson($statusUrl, $token);
            $state = strtolower((string)($status['status'] ?? ''));
            if ($state === 'completed') {
                $videoUrl = trim((string)($status['videoUrl'] ?? ''));
                if ($videoUrl === '') {
                    throw new RuntimeException('MeiGen hoàn tất nhưng không trả về videoUrl.');
                }
                return $this->saveVideo($contentId, $this->binaryFromUrl($videoUrl));
            }
            if ($state === 'failed') {
                throw new RuntimeException('MeiGen tạo video thất bại: ' . (string)($status['error'] ?? 'unknown error'));
            }
        } while (time() < $deadline);

        throw new RuntimeException('MeiGen tạo video quá thời gian chờ cho phép.');
    }

    private function generateKlingVideo(int $contentId, string $prompt): string
    {
        if (kling_access_key() === '' || kling_secret_key() === '') {
            throw new RuntimeException('Chưa cấu hình Kling Access Key/Secret Key cho site hiện tại. Vào Settings nhập Kling API trước khi tạo video.');
        }

        $payload = [
            'model_name' => kling_model() !== '' ? kling_model() : 'kling-v1-6',
            'prompt' => $prompt,
            'negative_prompt' => 'static poster, text-only slide, low quality, blurry, distorted hands, fake unreadable text, watermark, unsafe usage',
            'cfg_scale' => 0.5,
            'mode' => kling_mode(),
            'aspect_ratio' => video_aspect_ratio() !== '' ? video_aspect_ratio() : '9:16',
            'duration' => (string)max(5, min(10, video_duration_seconds())),
        ];

        $created = $this->postKlingJson(kling_base_url() . '/v1/videos/text2video', $payload);
        $taskId = (string)($created['data']['task_id'] ?? '');
        if ($taskId === '') {
            throw new RuntimeException('Kling API không trả về task_id khi tạo video.');
        }

        $deadline = time() + max(300, image_timeout_seconds());
        $statusUrl = kling_base_url() . '/v1/videos/text2video/' . rawurlencode($taskId);
        do {
            sleep(5);
            $status = $this->getKlingJson($statusUrl);
            $state = strtolower((string)($status['data']['task_status'] ?? ''));
            if (in_array($state, ['succeed', 'success', 'completed'], true)) {
                $videoUrl = (string)($status['data']['task_result']['videos'][0]['url'] ?? '');
                if ($videoUrl === '') {
                    throw new RuntimeException('Kling hoàn tất nhưng không trả về video URL.');
                }
                return $this->saveVideo($contentId, $this->binaryFromUrl($videoUrl));
            }
            if (in_array($state, ['failed', 'fail'], true)) {
                $message = (string)($status['data']['task_status_msg'] ?? $status['message'] ?? 'unknown error');
                throw new RuntimeException('Kling tạo video thất bại: ' . $message);
            }
        } while (time() < $deadline);

        throw new RuntimeException('Kling tạo video quá thời gian chờ cho phép.');
    }

    private function generateLocalPromoVideo(int $contentId, array $content): string
    {
        $ffmpeg = trim((string)shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            throw new RuntimeException('Server chua co ffmpeg de tao video local.');
        }

        [$width, $height] = $this->parseVideoSize(video_size());
        $duration = max(4, min(30, video_duration_seconds()));
        $uploadDir = BASE_PATH . '/backend/public/uploads/videos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $tmpDir = STORAGE_PATH . '/tmp/video-content-' . $contentId . '-' . bin2hex(random_bytes(4));
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $title = $this->wrapText((string)($content['title'] ?? 'Sản phẩm nổi bật'), 24, 3);
        $body = $this->wrapText($this->cleanBody((string)($content['body'] ?? '')), 34, 5);
        $cta = $this->wrapText((string)($content['call_to_action'] ?? 'Xem link mua ngay trong phần mô tả'), 26, 2);

        $titleFile = $tmpDir . '/title.txt';
        $bodyFile = $tmpDir . '/body.txt';
        $ctaFile = $tmpDir . '/cta.txt';
        file_put_contents($titleFile, $title, LOCK_EX);
        file_put_contents($bodyFile, $body, LOCK_EX);
        file_put_contents($ctaFile, $cta, LOCK_EX);

        $font = $this->fontFile();
        $fileName = 'content-' . $contentId . '-' . date('Ymd-His') . '.mp4';
        $outputPath = $uploadDir . '/' . $fileName;

        $filter = implode(',', [
            "drawbox=x=0:y=0:w=iw:h=ih:color=#111827@1:t=fill",
            "drawbox=x=0:y=0:w=iw:h=ih:color=#f97316@0.16:t=fill",
            $this->drawTextFilter($font, $titleFile, 50, 'white', '(w-text_w)/2', '150', 'black@0.35'),
            $this->drawTextFilter($font, $bodyFile, 32, '#f8fafc', '70', '430', 'black@0.25'),
            $this->drawTextFilter($font, $ctaFile, 40, '#fde68a', '(w-text_w)/2', 'h-260', 'black@0.45'),
            "drawtext=fontfile=" . $this->ffmpegQuote($font) . ":text='MMO Affiliate':fontcolor=#fb923c:fontsize=28:x=40:y=h-80"
        ]);

        $cmd = sprintf(
            '%s -y -f lavfi -i %s -vf %s -t %d -r 30 -pix_fmt yuv420p -c:v libx264 -movflags +faststart %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg('color=c=0x111827:s=' . $width . 'x' . $height . ':d=' . $duration),
            escapeshellarg($filter),
            $duration,
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $exitCode);
        $this->cleanupDir($tmpDir);

        if ($exitCode !== 0 || !is_file($outputPath) || filesize($outputPath) <= 0) {
            throw new RuntimeException('Tao video that bai: ' . trim(implode("\n", array_slice($output, -8))));
        }
        chmod($outputPath, 0644);

        return '/uploads/videos/' . $fileName;
    }

    private function saveVideo(int $contentId, string $binary): string
    {
        $uploadDir = BASE_PATH . '/backend/public/uploads/videos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'content-' . $contentId . '-' . date('Ymd-His') . '.mp4';
        $path = $uploadDir . '/' . $fileName;
        file_put_contents($path, $binary, LOCK_EX);
        chmod($path, 0644);
        return '/uploads/videos/' . $fileName;
    }

    private function klingJwt(): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => kling_access_key(),
            'exp' => $now + 1800,
            'nbf' => $now - 5,
        ];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), kling_secret_key(), true);
        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function postKlingJson(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->klingJwt(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);
        return $this->decodeKlingHttpJson($ch, 'Kling video API');
    }

    private function getKlingJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->klingJwt()],
            CURLOPT_TIMEOUT => 30,
        ]);
        return $this->decodeKlingHttpJson($ch, 'Kling video status');
    }

    private function decodeKlingHttpJson($ch, string $label): array
    {
        $decoded = $this->decodeHttpJson($ch, $label);
        $code = (int)($decoded['code'] ?? 0);
        if ($code !== 0) {
            throw new RuntimeException($label . ' lỗi ' . $code . ': ' . (string)($decoded['message'] ?? 'unknown error'));
        }
        return $decoded;
    }

    private function postMeiGenJson(string $url, array $payload, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 60,
        ]);
        return $this->decodeHttpJson($ch, 'MeiGen video API');
    }

    private function getMeiGenJson(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT => 30,
        ]);
        return $this->decodeHttpJson($ch, 'MeiGen video status');
    }

    private function decodeHttpJson($ch, string $label): array
    {
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException($label . ' loi ket noi: ' . $error);
        }
        $decoded = json_decode((string)$response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($decoded) ? (string)($decoded['error'] ?? $decoded['message'] ?? $response) : (string)$response;
            if ($httpCode === 402 && stripos($message, 'premium model') !== false) {
                throw new RuntimeException('MeiGen báo model video này là Premium và cần purchased credits. Daily free credits chỉ dùng cho basic models, không đủ để tạo video AI. Boss cần mua credits MeiGen hoặc đổi Video Provider về Local FFmpeg để tạo video MVP không tốn credit.');
            }
            if (stripos($label, 'Kling') !== false && stripos($message, 'Account balance not enough') !== false) {
                throw new RuntimeException('Kling báo tài khoản/API không đủ số dư để tạo video. Boss cần nạp credit Kling hoặc đổi Video Provider về Local FFmpeg để tạo video MVP không tốn credit.');
            }
            throw new RuntimeException($label . ' HTTP ' . $httpCode . ': ' . $message);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException($label . ' tra ve JSON khong hop le.');
        }
        return $decoded;
    }

    private function binaryFromUrl(string $url): string
    {
        $binary = @file_get_contents($url);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Không tải được video từ URL provider.');
        }
        return (string)$binary;
    }

    private function referenceImagesForContent(array $content): array
    {
        if (($content['media_type'] ?? '') !== 'image' || empty($content['media_url'] ?? '')) {
            return [];
        }
        $url = app_absolute_url((string)$content['media_url']);
        return preg_match('#^https://#i', $url) === 1 ? [$url] : [];
    }

    private function buildVideoPrompt(array $content, array $product = []): string
    {
        $promptService = new PromptTemplateService();
        $platform = (string)($content['platform'] ?? $content['channel_type'] ?? 'general');
        $platformArg = $platform !== 'general' ? $platform : null;
        $rendered = $promptService->renderForProduct('video', $product, $content, $platformArg);
        if ($rendered !== null) {
            return $rendered;
        }

        // Fallback hardcode khi chưa có template trong DB
        $productName = trim((string)($product['product_name'] ?? $content['title'] ?? 'sản phẩm'));
        $price = (float)($product['price'] ?? 0);
        $sold = (int)($product['sold_count'] ?? 0);
        $platform = (string)($product['source_platform'] ?? 'affiliate');
        $benefits = $this->extractBenefitBullets((string)($content['body'] ?? ''));

        return implode("\n", array_filter([
            'Create a short vertical product review video, NOT a text-only graphic.',
            'Style: realistic UGC/lifestyle product review for Facebook Reels/TikTok, natural handheld camera, smooth cinematic movement, bright clean Vietnamese e-commerce aesthetic.',
            'Main subject/product: ' . $productName . '.',
            $price > 0 ? 'Show the product as an affordable deal, approximate price context: ' . number_format($price, 0, ',', '.') . ' VND.' : '',
            $sold > 0 ? 'Social proof: product has high sales, about ' . number_format($sold) . ' sold.' : '',
            'Platform/deal context: ' . $platform . ' affiliate product.',
            'Scene plan: 1) close-up beauty shot of the product on a clean desk, 2) a relatable young Vietnamese user picks it up/unboxes it, 3) user demonstrates using the product in a real daily situation, 4) close-up of useful details/features, 5) satisfied reaction and final deal/recommendation moment.',
            $benefits !== '' ? 'Benefits to visualize: ' . $benefits : '',
            'Human/user requirement: include a real-looking person using or reviewing the product naturally; hands, face/reaction, and practical usage are welcome when appropriate.',
            'Avoid: do not create a static poster, do not make mostly text on screen, do not show fake logos, do not invent technical specs that are not visible, do not show unsafe usage.',
            'Text overlay: minimal only, Vietnamese, short phrases like “Đáng mua?”, “Review nhanh”, “Săn deal hôm nay”; keep product visuals and user action as the focus.',
            'Camera: vertical 9:16, smooth push-in, slight pan, product closeups, natural lighting, clean background, modern social-commerce style.',
            'Call to action feeling: ' . (string)($content['call_to_action'] ?? 'xem link mua trong phần mô tả'),
        ]));
    }

    private function extractBenefitBullets(string $body): string
    {
        $body = preg_replace('/\s+/u', ' ', trim($body)) ?? '';
        if ($body === '') {
            return '';
        }
        return mb_substr($body, 0, 350);
    }

    private function parseVideoSize(string $size): array
    {
        if (preg_match('/^(\d{3,4})x(\d{3,4})$/', trim($size), $matches) === 1) {
            return [(int)$matches[1], (int)$matches[2]];
        }
        return [720, 1280];
    }

    private function cleanBody(string $body): string
    {
        $body = preg_replace('/\s+/u', ' ', trim($body)) ?? '';
        return mb_substr($body, 0, 260);
    }

    private function wrapText(string $text, int $width, int $maxLines): string
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate) > $width && $line !== '') {
                $lines[] = $line;
                $line = $word;
                if (count($lines) >= $maxLines) {
                    break;
                }
                continue;
            }
            $line = $candidate;
        }
        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }
        return implode("\n", array_slice($lines, 0, $maxLines));
    }

    private function fontFile(): string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }
        throw new RuntimeException('Khong tim thay font TTF de render chu len video.');
    }

    private function drawTextFilter(string $font, string $textFile, int $size, string $color, string $x, string $y, string $boxColor): string
    {
        return 'drawtext=fontfile=' . $this->ffmpegQuote($font)
            . ':textfile=' . $this->ffmpegQuote($textFile)
            . ':fontcolor=' . $color
            . ':fontsize=' . $size
            . ':line_spacing=12:x=' . $x
            . ':y=' . $y
            . ':box=1:boxcolor=' . $boxColor
            . ':boxborderw=24';
    }

    private function ffmpegQuote(string $value): string
    {
        return str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $value);
    }

    private function cleanupDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
