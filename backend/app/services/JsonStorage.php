<?php

declare(strict_types=1);

final class JsonStorage
{
    public function read(string $fileName): array
    {
        $path = DATA_PATH . '/' . $fileName;
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    public function write(string $fileName, array $payload): void
    {
        $path = DATA_PATH . '/' . $fileName;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
