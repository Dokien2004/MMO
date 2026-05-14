<?php
/**
 * Analytics & AI Radar Dashboard.
 * Shows product scoring, trend charts, and AI recommendations.
 *
 * Variables: $scoreSummary, $scoreDistribution, $topRecommendations, $productSummary
 */
$totalScored = (int)($scoreSummary['total_scored'] ?? 0);
$avgScore = round((float)($scoreSummary['avg_score'] ?? 0), 1);
$strongBuy = (int)($scoreSummary['strong_buy_count'] ?? 0);
$buy = (int)($scoreSummary['buy_count'] ?? 0);
$rising = (int)($scoreSummary['rising_count'] ?? 0);
?>

<div class="hero-card">
    <div class="hero-row">
        <div>
            <div class="page-kicker">AI Product Intelligence</div>
            <h2 class="hero-title">Phân tích & Dự đoán</h2>
            <p class="hero-subtitle">Sử dụng AI để chấm điểm, phân tích xu hướng và dự đoán sản phẩm tiềm năng.</p>
        </div>
        <div class="hero-actions">
            <button class="btn btn-primary" onclick="runScoring()" id="btn-run-scoring">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Chấm điểm AI
            </button>
            <a class="btn btn-accent" href="<?= url('/scraper') ?>">Product Radar</a>
        </div>
    </div>
</div>

<!-- Score Summary Cards -->
<div class="stats-grid">
    <div class="stat-card accent">
        <div class="label">Đã chấm điểm</div>
        <div class="value"><?= $totalScored ?></div>
        <div class="sub">sản phẩm</div>
    </div>
    <div class="stat-card success">
        <div class="label">Điểm trung bình</div>
        <div class="value"><?= $avgScore ?></div>
        <div class="sub">/ 100</div>
    </div>
    <div class="stat-card warning">
        <div class="label">Strong Buy</div>
        <div class="value"><?= $strongBuy ?></div>
        <div class="sub">điểm ≥ 80</div>
    </div>
    <div class="stat-card purple">
        <div class="label">Đang tăng</div>
        <div class="value"><?= $rising ?></div>
        <div class="sub">trend rising</div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid-2">
    <!-- Score Distribution Chart -->
    <div class="card">
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            Phân bổ điểm số
        </div>
        <div style="position:relative;height:280px;">
            <canvas id="scoreDistributionChart"></canvas>
        </div>
    </div>

    <!-- Recommendation Breakdown -->
    <div class="card">
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
            Tổng hợp khuyến nghị
        </div>
        <div class="status-stack" style="gap:12px;padding:16px 0;">
            <div class="status-line">
                <span>🟢 Strong Buy (≥80)</span>
                <strong style="color:var(--success)"><?= $strongBuy ?> SP</strong>
            </div>
            <div class="status-line">
                <span>🔵 Buy (60-79)</span>
                <strong style="color:var(--accent)"><?= $buy ?> SP</strong>
            </div>
            <div class="status-line">
                <span>🟡 Hold (35-59)</span>
                <strong style="color:var(--warning)"><?= (int)($scoreSummary['hold_count'] ?? 0) ?> SP</strong>
            </div>
            <div class="status-line">
                <span>🔴 Avoid (&lt;35)</span>
                <strong style="color:var(--danger,#e74c3c)"><?= (int)($scoreSummary['avoid_count'] ?? 0) ?> SP</strong>
            </div>
        </div>
        <!-- Score gauge -->
        <div style="text-align:center;padding:16px 0;">
            <div style="font-size:48px;font-weight:700;color:<?= $avgScore >= 60 ? 'var(--success)' : ($avgScore >= 40 ? 'var(--warning)' : 'var(--danger,#e74c3c)') ?>;">
                <?= $avgScore ?>
            </div>
            <div class="sub">Điểm trung bình hệ thống</div>
        </div>
    </div>
</div>

<!-- Top Recommendations Table -->
<div class="card mb-24">
    <div class="section-heading">
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--accent);fill:none;stroke-width:2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Top sản phẩm tiềm năng
        </div>
        <div class="section-note"><?= count($topRecommendations) ?> sản phẩm đã chấm điểm</div>
    </div>

    <?php if (empty($topRecommendations)): ?>
        <div class="empty-state">
            <p>Chưa có dữ liệu chấm điểm. Nhấn <strong>Chấm điểm AI</strong> để bắt đầu phân tích.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Điểm</th>
                        <th>Khuyến nghị</th>
                        <th>Xu hướng</th>
                        <th>Tốc độ bán</th>
                        <th>Giá</th>
                        <th>Lượt bán</th>
                        <th style="width:90px;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topRecommendations as $rec): ?>
                    <?php
                    $score = round((float)($rec['overall_score'] ?? 0), 1);
                    $scoreColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#4f46e5' : ($score >= 35 ? '#f59e0b' : '#ef4444'));
                    $recLabel = match($rec['recommendation'] ?? 'hold') {
                        'strong_buy' => '<span class="badge" style="background:#10b981;color:#fff">Strong Buy</span>',
                        'buy' => '<span class="badge" style="background:#4f46e5;color:#fff">Buy</span>',
                        'hold' => '<span class="badge" style="background:#f59e0b;color:#000">Hold</span>',
                        'avoid' => '<span class="badge" style="background:#ef4444;color:#fff">Avoid</span>',
                        default => '<span class="badge">—</span>',
                    };
                    $trendIcon = match($rec['trend_direction'] ?? 'stable') {
                        'rising' => '📈',
                        'declining' => '📉',
                        default => '➡️',
                    };
                    $aiInfo = $rec['ai_analysis'] ?? [];
                    ?>
                    <tr>
                        <td>
                            <strong><?= e((string)($rec['product_name'] ?? '')) ?></strong>
                            <div class="sub"><?= e((string)($rec['source_platform'] ?? '')) ?></div>
                            <?php if (!empty($aiInfo['reasoning'])): ?>
                                <div class="sub text-muted" style="font-size:11px;margin-top:2px;"><?= e((string)$aiInfo['reasoning']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight:700;font-size:18px;color:<?= $scoreColor ?>"><?= $score ?></span>
                        </td>
                        <td><?= $recLabel ?></td>
                        <td><?= $trendIcon ?> <?= e((string)($rec['trend_direction'] ?? 'stable')) ?></td>
                        <td><span class="metric-pill"><?= number_format((float)($rec['sales_velocity'] ?? 0), 1) ?>/ngày</span></td>
                        <td><?= number_format((float)($rec['price'] ?? 0), 0, ',', '.') ?>₫</td>
                        <td><span class="metric-pill"><?= number_format((int)($rec['sold_count'] ?? 0)) ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="pickProduct(<?= (int)($rec['product_id'] ?? $rec['id'] ?? 0) ?>)" style="font-size:11px;padding:4px 10px;">🎯 Chọn</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Score Distribution Doughnut Chart
    const distCtx = document.getElementById('scoreDistributionChart');
    if (distCtx) {
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: ['Xuất sắc (≥80)', 'Tốt (60-79)', 'Trung bình (40-59)', 'Yếu (<40)'],
                datasets: [{
                    data: [
                        <?= (int)($scoreDistribution['excellent'] ?? 0) ?>,
                        <?= (int)($scoreDistribution['good'] ?? 0) ?>,
                        <?= (int)($scoreDistribution['average'] ?? 0) ?>,
                        <?= (int)($scoreDistribution['poor'] ?? 0) ?>
                    ],
                    backgroundColor: ['#10b981', '#4f46e5', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: getComputedStyle(document.body).getPropertyValue('--text') || '#1e293b', padding: 16 }
                    }
                }
            }
        });
    }
});

// Run Scoring
function runScoring() {
    const btn = document.getElementById('btn-run-scoring');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Đang chấm điểm...';

    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '<?= e(csrf_token()) ?>');
    fd.append('limit', '30');

    fetch('<?= url('/scores/run') ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (typeof toastr !== 'undefined') {
                res.success ? toastr.success(res.message) : toastr.error(res.message);
            } else {
                alert(res.message);
            }
            if (res.success) setTimeout(() => location.reload(), 1500);
        })
        .catch(err => {
            alert('Lỗi: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Chấm điểm AI';
        });
}

// Pick product → My Products
function pickProduct(productId) {
    if (!productId) return;
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '<?= e(csrf_token()) ?>');
    fd.append('source_product_id', productId);

    fetch('<?= url('/my-products/pick') ?>', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (typeof toastr !== 'undefined') {
                res.success ? toastr.success(res.message) : toastr.error(res.message);
            } else {
                alert(res.message);
            }
        })
        .catch(err => alert('Lỗi: ' + err.message));
}
</script>
