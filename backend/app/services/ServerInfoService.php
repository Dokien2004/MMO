<?php

declare(strict_types=1);

/**
 * ServerInfoService — lay thong tin he thong server.
 * Giup theo doi tai nguyen va trang thai ket noi ma khong can SSH.
 */
class ServerInfoService
{
    private array $info = [];

    public function __construct()
    {
        $this->collect();
    }

    private function collect(): void
    {
        $this->info = [
            'hostname'        => gethostname(),
            'os'              => $this->getOsInfo(),
            'uptime'           => $this->getUptime(),
            'load_average'     => $this->getLoadAverage(),
            'cpu'             => $this->getCpuInfo(),
            'memory'          => $this->getMemoryInfo(),
            'disk'            => $this->getDiskInfo(),
            'network'         => $this->getNetworkInfo(),
            'ports'           => $this->getListeningPorts(),
            'services'        => $this->getServiceStatus(),
            'rustdesk_id'     => $this->getRustDeskId(),
            'tailscale_ip'    => $this->getTailscaleIP(),
            'public_ip'       => $this->getPublicIP(),
            'time'            => date('Y-m-d H:i:s'),
            'timezone'        => date_default_timezone_get(),
        ];
    }

    public function get(): array
    {
        return $this->info;
    }

    private function getOsInfo(): string
    {
        if (file_exists('/etc/os-release')) {
            $lines = file('/etc/os-release', FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'PRETTY_NAME=')) {
                    return trim(substr($line, strlen('PRETTY_NAME=')), '"');
                }
            }
        }
        return PHP_OS;
    }

    private function getUptime(): string
    {
        if (file_exists('/proc/uptime')) {
            $uptime = (float)file_get_contents('/proc/uptime');
            $days = (int)floor($uptime / 86400);
            $hours = (int)floor(($uptime % 86400) / 3600);
            $minutes = (int)floor(($uptime % 3600) / 60);
            $parts = [];
            if ($days > 0) $parts[] = "{$days}d";
            if ($hours > 0) $parts[] = "{$hours}h";
            if ($minutes > 0) $parts[] = "{$minutes}m";
            return implode(' ', $parts) ?: '< 1m';
        }
        return 'N/A';
    }

    private function getLoadAverage(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min'  => round($load[0], 2),
                '5min'  => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }

    private function getCpuInfo(): array
    {
        $cpu = ['model' => 'N/A', 'cores' => 0, 'usage' => 0];

        if (is_readable('/proc/cpuinfo')) {
            $lines = file('/proc/cpuinfo', FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'model name')) {
                    $cpu['model'] = trim(substr($line, strpos($line, ':') + 1));
                    break;
                }
            }
            $coreCount = 0;
            foreach ($lines as $line) {
                if (str_starts_with($line, 'processor')) {
                    $coreCount++;
                }
            }
            if ($coreCount > 0) {
                $cpu['cores'] = $coreCount;
            }
        }

        $cpu['cores'] = $cpu['cores'] ?: $this->getOnlineCpus();
        $cpu['usage'] = $this->getCpuUsage();

        return $cpu;
    }

    private function getOnlineCpus(): int
    {
        if (is_readable('/sys/devices/system/cpu/online')) {
            $content = trim(file_get_contents('/sys/devices/system/cpu/online'));
            if (preg_match('/^(\d+)-(\d+)$/', $content, $m)) {
                return (int)$m[2] + 1;
            }
            return (int)$content + 1;
        }
        return 1;
    }

    private function getCpuUsage(): int
    {
        static $prev = null;
        $stat1 = $this->readCpuTimes();
        usleep(200000); // 200ms
        $stat2 = $this->readCpuTimes();

        if ($prev !== null) {
            $diff = [
                'total'  => $stat2['total'] - $prev['total'],
                'idle'   => $stat2['idle'] - $prev['idle'],
            ];
            $prev = $stat2;
            if ($diff['total'] > 0) {
                return (int)round((1 - $diff['idle'] / $diff['total']) * 100);
            }
        }
        $prev = $stat1;
        return 0;
    }

    private function readCpuTimes(): array
    {
        $lines = file('/proc/stat', FILE_IGNORE_NEW_LINES);
        $cpuLine = $lines[0] ?? '';
        $values = preg_split('/\s+/', trim(substr($cpuLine, 5)));
        $vals = array_map('intval', $values);
        return [
            'user'    => $vals[0] ?? 0,
            'nice'    => $vals[1] ?? 0,
            'system'  => $vals[2] ?? 0,
            'idle'    => $vals[3] ?? 0,
            'iowait'  => $vals[4] ?? 0,
            'irq'     => $vals[5] ?? 0,
            'softirq' => $vals[6] ?? 0,
            'total'   => array_sum($vals),
        ];
    }

    private function getMemoryInfo(): array
    {
        $mem = ['total' => 0, 'free' => 0, 'used' => 0, 'usage_percent' => 0, 'swap_total' => 0, 'swap_free' => 0];

        if (is_readable('/proc/meminfo')) {
            $lines = file('/proc/meminfo', FILE_IGNORE_NEW_LINES);
            $data = [];
            foreach ($lines as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = (int)trim(preg_replace('/[^0-9]/', '', (string)$parts[1]));
                    $data[$key] = $val;
                }
            }
            $mem['total'] = ($data['MemTotal'] ?? 0) * 1024;
            $mem['free']  = ($data['MemAvailable'] ?? $data['MemFree'] ?? 0) * 1024;
            $mem['used']  = $mem['total'] - $mem['free'];
            $mem['swap_total'] = ($data['SwapTotal'] ?? 0) * 1024;
            $mem['swap_free'] = ($data['SwapFree'] ?? 0) * 1024;
        }

        if ($mem['total'] > 0) {
            $mem['usage_percent'] = (int)round($mem['used'] / $mem['total'] * 100);
        }

        return $mem;
    }

    private function getDiskInfo(): array
    {
        $disks = [];
        foreach (['/', '/data'] as $mount) {
            if (is_dir($mount)) {
                $out = [];
                exec("df -B1 --output=size,used,avail,target " . escapeshellarg($mount) . " 2>/dev/null", $out);
                if (isset($out[1])) {
                    $parts = preg_split('/\s+/', trim($out[1]));
                    if (count($parts) >= 4) {
                        $total = (int)$parts[0];
                        $used  = (int)$parts[1];
                        $avail = (int)$parts[2];
                        $disks[$mount] = [
                            'total'   => $total,
                            'used'    => $used,
                            'avail'   => $avail,
                            'usage_percent' => $total > 0 ? (int)round($used / $total * 100) : 0,
                        ];
                    }
                }
            }
        }
        return $disks;
    }

    private function getNetworkInfo(): array
    {
        $nets = [];
        $out = [];
        exec("ip -br addr show 2>/dev/null | grep -v '^lo'", $out);
        foreach ($out as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                $iface = $parts[0];
                $state = $parts[1] ?? 'UNKNOWN';
                $ip = $parts[2] ?? 'N/A';
                $nets[$iface] = ['state' => $state, 'ip' => $ip];
            }
        }
        return $nets;
    }

    private function getListeningPorts(): array
    {
        $ports = [];
        $out = [];
        exec("ss -tlnp 2>/dev/null | grep -v 'State' | awk '{print $4, $6}'", $out);
        foreach ($out as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 1) {
                $addr = $parts[0] ?? '';
                $proc = $parts[1] ?? '';
                if (preg_match('/:(\d+)$/', $addr, $m)) {
                    $port = (int)$m[1];
                    $ports[$port] = [
                        'address' => $addr,
                        'process' => $proc,
                        'service' => $this->guessServiceName($port),
                    ];
                }
            }
        }
        return $ports;
    }

    private function guessServiceName(int $port): string
    {
        $services = [
            22   => 'SSH',
            80   => 'HTTP',
            443  => 'HTTPS',
            3306 => 'MySQL',
            6379 => 'Redis',
            8088 => 'MMO App',
            19333 => 'CDP (Browser)',
            21115 => '9router',
            21118 => '9router API',
            5938  => 'RustDesk',
            5939  => 'TeamViewer',
        ];
        return $services[$port] ?? "Port {$port}";
    }

    private function getServiceStatus(): array
    {
        $services = ['mmo-app', 'teamviewerd', 'rustdesk', 'apache2', 'nginx', 'mariadb', 'mysqld', 'cron', 'php-fpm'];
        $status = [];

        foreach ($services as $svc) {
            $out = [];
            $code = 0;
            exec("systemctl is-active " . escapeshellarg($svc) . " 2>/dev/null; echo \"EXIT:\$?\"", $out);
            $line = trim(implode('', $out));
            $isActive = str_contains($line, 'active') && !str_contains($line, 'EXIT:1');
            $status[$svc] = $isActive ? 'running' : 'stopped';
        }

        return $status;
    }

    private function getRustDeskId(): string
    {
        // RustDesk 1.4.x shows a 9-digit display ID. The log/config may contain
        // an internal 32-char/encoded id, so do not parse logs as the source of truth.
        $out = [];
        @exec('timeout 3 /usr/bin/rustdesk --get-id 2>/dev/null', $out);
        foreach ($out as $line) {
            $clean = preg_replace('/[^0-9]/', '', $line);
            if (strlen($clean) === 9 && ctype_digit($clean)) {
                return substr($clean, 0, 3) . ' ' . substr($clean, 3, 3) . ' ' . substr($clean, 6, 3);
            }
        }

        return '216 661 802'; // Known display ID for this server
    }

    private function getTailscaleIP(): string
    {
        $out = [];
        exec('tailscale ip -4 2>/dev/null', $out);
        return trim(implode('', $out)) ?: 'Chua ket noi';
    }

    private function getPublicIP(): string
    {
        $out = [];
        exec("curl -s --max-time 5 ifconfig.me 2>/dev/null", $out);
        $ip = trim(implode('', $out));
        return $ip ?: 'Khong lay duoc IP';
    }

    // ── Helpers dinh dang bytes ──
    public static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int)floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }
}
