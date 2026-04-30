<?php

declare(strict_types=1);

final class IntegrationConfigService
{
    private string $path;

    /** @var array<string, string> */
    private array $allowedKeys = [
        'OPENAI_API_KEY' => 'OpenAI API Key',
        'OPENAI_MODEL' => 'OpenAI Model',
        'OPENAI_BASE_URL' => 'OpenAI-compatible Base URL',
        'GEMINI_API_KEY' => 'Gemini API Key',
        'GEMINI_MODEL' => 'Gemini Model',
        'FACEBOOK_PAGE_ID' => 'Facebook Page ID',
        'FACEBOOK_PAGE_ACCESS_TOKEN' => 'Facebook Page Access Token',
    ];

    public function __construct()
    {
        $this->path = __DIR__ . '/../config/local.php';
    }

    public function current(): array
    {
        return [
            'OPENAI_API_KEY' => OPENAI_API_KEY,
            'OPENAI_MODEL' => openai_model(),
            'OPENAI_BASE_URL' => openai_base_url(),
            'GEMINI_API_KEY' => GEMINI_API_KEY,
            'GEMINI_MODEL' => gemini_model(),
            'FACEBOOK_PAGE_ID' => FACEBOOK_PAGE_ID,
            'FACEBOOK_PAGE_ACCESS_TOKEN' => FACEBOOK_PAGE_ACCESS_TOKEN,
        ];
    }

    public function masked(): array
    {
        $values = $this->current();
        foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'FACEBOOK_PAGE_ACCESS_TOKEN'] as $secretKey) {
            $values[$secretKey . '_MASKED'] = $this->mask((string)($values[$secretKey] ?? ''));
            $values[$secretKey . '_SET'] = trim((string)($values[$secretKey] ?? '')) !== '';
            unset($values[$secretKey]);
        }
        return $values;
    }

    public function save(array $input): array
    {
        $current = $this->current();
        $next = $current;

        foreach ($this->allowedKeys as $key => $_label) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = trim((string)$input[$key]);
            if ($value === '') {
                continue;
            }
            if ($value === '__CLEAR__') {
                $next[$key] = '';
                continue;
            }
            $next[$key] = $value;
        }

        $this->writeLocalConfig($next);
        return $next;
    }

    private function writeLocalConfig(array $values): void
    {
        $existing = $this->parseExistingConstants();
        $merged = array_merge($existing, $values);

        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        $orderedKeys = [
            'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
            'OPENAI_API_KEY', 'OPENAI_MODEL', 'OPENAI_BASE_URL',
            'GEMINI_API_KEY', 'GEMINI_MODEL',
            'FACEBOOK_PAGE_ID', 'FACEBOOK_PAGE_ACCESS_TOKEN',
        ];

        foreach ($orderedKeys as $key) {
            if (!array_key_exists($key, $merged)) {
                continue;
            }
            $value = $merged[$key];
            if ($key === 'DB_PORT') {
                $content .= "const {$key} = " . (int)$value . ";\n";
                continue;
            }
            $content .= "const {$key} = '" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$value) . "';\n";
        }

        $tmp = $this->path . '.tmp';
        file_put_contents($tmp, $content, LOCK_EX);
        chmod($tmp, 0600);
        rename($tmp, $this->path);
    }

    private function parseExistingConstants(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $content = (string)file_get_contents($this->path);
        preg_match_all("/const\\s+([A-Z0-9_]+)\\s*=\\s*(?:'([^']*)'|(\\d+))\\s*;/", $content, $matches, PREG_SET_ORDER);
        $values = [];
        foreach ($matches as $match) {
            $values[$match[1]] = isset($match[3]) && $match[3] !== ''
                ? (int)$match[3]
                : stripcslashes((string)($match[2] ?? ''));
        }
        return $values;
    }

    private function mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Chưa cấu hình';
        }
        if (strlen($value) <= 10) {
            return str_repeat('•', strlen($value));
        }
        return substr($value, 0, 4) . '••••••' . substr($value, -4);
    }
}
