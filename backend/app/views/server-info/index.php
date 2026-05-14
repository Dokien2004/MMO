<div class="page-header">
    <div>
        <div class="page-kicker">System Monitor</div>
        <h2>Thông tin Server</h2>
        <p>Tình trạng tài nguyên, kết nối mạng và các service đang chạy trên máy chủ.</p>
    </div>
    <div class="btn-group">
        <a class="btn btn-secondary" href="<?= url('/server-info') ?>">
            🔄 Làm mới
        </a>
    </div>
</div>

<?php
$si = $serverInfo;
$mem = $si['memory'];
$cpu = $si['cpu'];
$disk = $si['disk'];
$net = $si['network'];
$ports = $si['ports'];
$services = $si['services'];
$load = $si['load_average'];
?>

<div class="grid-3">
    <!-- Hostname & OS -->
    <div class="card">
        <div class="card-title">🖥️ Hệ thống</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Hostname</span>
                <span class="info-value"><?= e($si['hostname']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Hệ điều hành</span>
                <span class="info-value"><?= e($si['os']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Uptime</span>
                <span class="info-value"><?= e($si['uptime']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Time</span>
                <span class="info-value"><?= e($si['time']) ?> (<?= e($si['timezone']) ?>)</span>
            </div>
        </div>
    </div>

    <!-- CPU -->
    <div class="card">
        <div class="card-title">⚙️ CPU</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">Model</span>
                <span class="info-value"><?= e($cpu['model']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Cores</span>
                <span class="info-value"><?= (int)$cpu['cores'] ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Load (1/5/15m)</span>
                <span class="info-value">
                    <span class="metric-pill"><?= $load['1min'] ?></span>
                    <span class="metric-pill"><?= $load['5min'] ?></span>
                    <span class="metric-pill"><?= $load['15min'] ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Usage</span>
                <span class="info-value">
                    <?php
                    $cpuUsage = (int)$cpu['usage'];
                    $cpuColor = $cpuUsage < 50 ? 'success' : ($cpuUsage < 80 ? 'warning' : 'danger');
                    ?>
                    <span class="metric-pill metric-<?= $cpuColor ?>"><?= $cpuUsage ?>%</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Memory -->
    <div class="card">
        <div class="card-title">🧠 RAM & Swap</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">RAM</span>
                <span class="info-value">
                    <?= ServerInfoService::formatBytes($mem['used']) ?> /
                    <?= ServerInfoService::formatBytes($mem['total']) ?>
                    <span class="metric-pill metric-<?= $mem['usage_percent'] < 70 ? 'success' : ($mem['usage_percent'] < 90 ? 'warning' : 'danger') ?>">
                        <?= $mem['usage_percent'] ?>%
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Swap</span>
                <span class="info-value">
                    <?= ServerInfoService::formatBytes($mem['swap_total'] - $mem['swap_free']) ?> /
                    <?= ServerInfoService::formatBytes($mem['swap_total']) ?>
                </span>
            </div>
        </div>
        <!-- RAM bar -->
        <div class="progress-bar-wrap mt-12">
            <div class="progress-bar-bar" style="width:<?= $mem['usage_percent'] ?>%; background: <?= $mem['usage_percent'] < 70 ? 'var(--color-success)' : ($mem['usage_percent'] < 90 ? 'var(--color-warning)' : 'var(--color-danger)') ?>"></div>
        </div>
    </div>
</div>

<!-- Disk -->
<div class="card mt-16">
    <div class="card-title">💾 Disk</div>
    <?php if (empty($disk)): ?>
        <div class="empty-state"><p>Không đọc được thông tin disk.</p></div>
    <?php else: ?>
        <div class="grid-2">
            <?php foreach ($disk as $mount => $info): ?>
                <div>
                    <div class="sub mb-8">
                        <strong><?= e($mount) ?></strong> —
                        <?= ServerInfoService::formatBytes($info['used']) ?> /
                        <?= ServerInfoService::formatBytes($info['total']) ?>
                        (<?= $info['usage_percent'] ?>% used)
                    </div>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-bar" style="width:<?= $info['usage_percent'] ?>%; background: <?= $info['usage_percent'] < 70 ? 'var(--color-success)' : ($info['usage_percent'] < 90 ? 'var(--color-warning)' : 'var(--color-danger)') ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Network -->
<div class="card mt-16">
    <div class="card-title">🌐 Network Interfaces</div>
    <?php if (empty($net)): ?>
        <div class="empty-state"><p>Không có thông tin network.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Interface</th><th>State</th><th>IP Address</th></tr></thead>
                <tbody>
                <?php foreach ($net as $iface => $info): ?>
                    <tr>
                        <td><strong><?= e($iface) ?></strong></td>
                        <td>
                            <?php if ($info['state'] === 'UP'): ?>
                                <span class="badge badge-success">UP</span>
                            <?php else: ?>
                                <span class="badge"><?= e($info['state']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($info['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Ports & Services -->
<div class="grid-2 mt-16">
    <!-- Listening Ports -->
    <div class="card">
        <div class="card-title">🔌 Listening Ports</div>
        <?php if (empty($ports)): ?>
            <div class="empty-state"><p>Không đọc được ports.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Port</th><th>Service</th></tr></thead>
                    <tbody>
                    <?php foreach ($ports as $port => $info): ?>
                        <tr>
                            <td><span class="metric-pill"><?= $port ?></span></td>
                            <td><?= e($info['service']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Services -->
    <div class="card">
        <div class="card-title">🚀 Services</div>
        <div class="status-stack">
            <?php foreach ($services as $svc => $state): ?>
                <div class="status-line">
                    <span><?= e($svc) ?></span>
                    <?php if ($state === 'running'): ?>
                        <span class="badge badge-success">running</span>
                    <?php else: ?>
                        <span class="badge badge-failed">stopped</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Remote Access -->
<div class="card mt-16">
    <div class="card-title">🔗 Kết nối từ xa</div>
    <div class="grid-3">
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">RustDesk ID</span>
                <span class="info-value">
                    <span class="metric-pill metric-accent"><?= e($si['rustdesk_id']) ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Tailscale IP</span>
                <span class="info-value">
                    <span class="metric-pill"><?= e($si['tailscale_ip']) ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Public IP</span>
                <span class="info-value"><?= e($si['public_ip']) ?></span>
            </div>
        </div>
    </div>
    <div class="hint-box mt-16">
        <strong>RustDesk:</strong> Dùng ID <strong><?= e($si['rustdesk_id']) ?></strong> để kết nối từ xa từ laptop của bạn.<br>
        <strong>Tailscale:</strong> Kết nối VPN riêng, an toàn hơn. IP: <?= e($si['tailscale_ip']) ?><br>
        <strong>Public IP:</strong> IP thực của server trên internet.
    </div>
</div>

<style>
.info-grid { display: flex; flex-direction: column; gap: 8px; }
.info-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.info-label { color: var(--color-sub); font-size: 0.85em; flex-shrink: 0; }
.info-value { font-weight: 500; text-align: right; word-break: break-all; }
.progress-bar-wrap { background: var(--color-border); border-radius: 4px; height: 8px; overflow: hidden; }
.progress-bar-bar { height: 100%; border-radius: 4px; transition: width 0.3s; }
.mt-12 { margin-top: 12px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
@media (max-width: 900px) { .grid-3 { grid-template-columns: 1fr; } }
.metric-pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 600; background: var(--color-border); }
.metric-accent { background: var(--color-accent-bg, #e0f0ff); color: var(--color-accent, #0066cc); }
.metric-success { background: #e6f4ea; color: #1e7e34; }
.metric-warning { background: #fff3e0; color: #e65100; }
.metric-danger { background: #fce8e6; color: #c5221f; }
</style>