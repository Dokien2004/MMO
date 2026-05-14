<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Product Radar — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    @media (max-width: 640px) {
        .page-header { flex-direction: column; gap: 10px }
        .page-header .hero-actions { width: 100%; justify-content: flex-start; flex-wrap: wrap }
        .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px }
        .charts-row { flex-direction: column !important }
        .tools-grid { grid-template-columns: 1fr !important }
        .quick-links { flex-wrap: wrap }
        .quick-links a { flex: 1; min-width: 120px; text-align: center; justify-content: center }
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch }
        .radar-form { flex-direction: column }
    }
    .charts-row { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap }
    .charts-row > * { flex: 1; min-width: 280px }
    .chart-card canvas { max-height: 240px }
    .tools-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px }
    .tool-card { padding: 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-elevated); text-decoration: none; color: inherit; display: block; transition: border-color .2s }
    .tool-card:hover { border-color: var(--accent) }
    .tool-card .tool-name { font-weight: 600; font-size: 14px; margin-bottom: 4px }
    .tool-card .tool-desc { font-size: 12px; color: var(--text-sec); margin-bottom: 6px }
    .tool-card .tool-tag { display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 20px; background: var(--bg); color: var(--accent); border: 1px solid var(--border) }
    .hot-cat-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; margin: 2px }
    .hot-cat-badge.hot { background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5 }
    .quick-links { display: flex; gap: 10px; flex-wrap: wrap; margin: 12px 0 }
    pre { background: var(--bg-elevated); padding: 12px; border-radius: 8px; font-size: 12px; overflow-x: auto; margin-bottom: 12px }
    </style>
</head>
<body>

<?php
$topProducts = $topProducts ?? [];
$radar = $productRadar ?? ['opportunities' => [], 'github_repos' => [], 'notes' => [], 'count' => 0];
$scraperSummary = $scraperSummary ?? [];
$productSummary = $productSummary ?? [];
$configs = $configs ?? [];

// Build chart data from topProducts
$chartProducts = array_slice($topProducts, 0, 10);
$chartLabels = array_map(static fn($p) => mb_substr($p['product_name'] ?? '', 0, 28), $chartProducts);
$soldData = array_map(static fn($p) => (int)($p['sold_count'] ?? 0), $chartProducts);
$priceDataK = array_map(static fn($p) => round(((float)($p['price'] ?? 0)) / 1000, 1), $chartProducts);
$scatterData = [];
for ($i = 0; $i < count($priceDataK); $i++) { $scatterData[] = ['x' => $priceDataK[$i], 'y' => $soldData[$i]]; }
?>
<div class="page-header">
    <div>
        <div class="page-kicker">Market Intelligence</div>
        <h2>Product Radar</h2>
        <p>Dashboard thị trường — biểu đồ từ dữ liệu cào thực, hot categories, top sản phẩm và công cụ.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-accent" href="<?= url('/scraper') ?>">Refresh</a>
        <a class="btn btn-purple" href="<?= url('/products') ?>">Sản phẩm</a>
    </div>
</div>

<!-- Quick links -->
<div class="quick-links">
    <a class="btn btn-primary" href="#charts">Charts</a>
    <a class="btn" href="#hot-cats">Hot Categories</a>
    <a class="btn btn-accent" href="#tools">Cong cu</a>
    <a class="btn btn-success" href="<?= url('/scraper/radar') ?>">Phân tích</a>
</div>

<!-- KPI -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card accent"><div class="label">SP đã cào</div><div class="value"><?= number_format((int)($productSummary['total'] ?? 0)) ?></div></div>
    <div class="stat-card success"><div class="label">Cào từ scraper</div><div class="value"><?= number_format((int)($scraperSummary['total_scraped'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="label">Cấu hình</div><div class="value"><?= count($configs) ?></div></div>
    <div class="stat-card purple"><div class="label">Radar items</div><div class="value"><?= (int)($radar['count'] ?? 0) ?></div></div>
</div>

<!-- Charts -->
<div id="charts"></div>
<div class="charts-row">
    <div class="card chart-card">
        <div class="card-title" style="font-size:14px">Top sản phẩm theo lượt bán</div>
        <canvas id="topSoldChart"></canvas>
    </div>
    <div class="card chart-card">
        <div class="card-title" style="font-size:14px">Giá vs Lượt bán</div>
        <canvas id="priceSoldChart"></canvas>
    </div>
</div>

<!-- Radar results -->
<?php if (!empty($radar['opportunities'])): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-title" style="font-size:15px">Kết quả phân tích Radar</div>
    <div class="table-wrap">
        <table class="table-main table-compact">
            <thead><tr><th style="width:50px">Điểm</th><th>Sản phẩm</th><th>Nhu cầu</th><th>Góc content</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($radar['opportunities'], 0, 5) as $item): ?>
                <?php $score = (int)($item['score'] ?? 0); $c = $score >= 75 ? '#22c55e' : ($score >= 55 ? '#f59e0b' : '#ef4444'); ?>
                <tr>
                    <td><span class="metric-pill" style="background:<?= $c ?>20;color:<?= $c ?>;font-weight:700"><?= $score ?></span></td>
                    <td><strong class="text-sm"><?= e(mb_substr($item['name'] ?? '', 0, 40)) ?></strong></td>
                    <td class="text-sm"><?= e($item['demand'] ?? '') ?></td>
                    <td class="text-sm"><?= e(mb_substr($item['content_angle'] ?? '', 0, 35)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Hot categories -->
<div class="card" id="hot-cats" style="margin-bottom:20px">
    <div class="card-title" style="font-size:15px">Hot Categories - Top bán theo ngành</div>
    <p class="text-muted text-sm" style="margin-bottom:12px">Sản phẩm xếp theo tong lượt bán. Nhung ngành co ban nhieu nhat.</p>
    <?php
    // Simple category grouping by name keywords
    $catGroups = ['Thoi trang' => 0, 'Dien tu' => 0, 'Am thanh' => 0, 'Suc khoe' => 0, 'Me & Be' => 0, 'Khac' => 0];
    $catTotal = ['Thoi trang' => 0, 'Dien tu' => 0, 'Am thanh' => 0, 'Suc khoe' => 0, 'Me & Be' => 0, 'Khac' => 0];
    foreach ($topProducts as $p) {
        $name = mb_strtolower($p['product_name'] ?? '');
        $sold = (int)($p['sold_count'] ?? 0);
        if (preg_match('/(?:ao|quan|vy|hoodie|blazer|vest|so mi|do bong)/u', $name)) { $catGroups['Thoi trang'] += $sold; $catTotal['Thoi trang']++; }
        elseif (preg_match('/(?:dien thoai|may tinh|laptop|tablet|smartwatch|smart tv)/u', $name)) { $catGroups['Dien tu'] += $sold; $catTotal['Dien tu']++; }
        elseif (preg_match('/(?:tai nghe|loa|micro|am thanh|cap|sac|cu|speaker|receiver|dac)/u', $name)) { $catGroups['Am thanh'] += $sold; $catTotal['Am thanh']++; }
        elseif (preg_match('/(?:my pham|son|dung|kem|serum|mat na|thuoc|tay trang)/u', $name)) { $catGroups['Suc khoe'] += $sold; $catTotal['Suc khoe']++; }
        elseif (preg_match('/(?:me & be|tre em|do tre em|me be)/u', $name)) { $catGroups['Me & Be'] += $sold; $catTotal['Me & Be']++; }
        else { $catGroups['Khac'] += $sold; $catTotal['Khac']++; }
    }
    arsort($catGroups);
    ?>
    <div style="margin-bottom:14px">
        <?php $i=0; foreach ($catGroups as $cat => $sold): $i++; if($sold==0) continue; ?>
            <span class="hot-cat-badge <?= $i<=2?'hot':($i<=4?'medium':'rising') ?>">
                <?= e($cat) ?> <?= number_format($sold) ?> ban (<?= $catTotal[$cat] ?> sp)
            </span>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table class="table-main table-compact">
            <thead><tr><th>Nghe</th><th>Tổng lượt bán</th><th>Số SP</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php $r=1; foreach ($catGroups as $cat => $sold): if($sold==0) continue; ?>
                <tr>
                    <td><strong><?= e($cat) ?></strong></td>
                    <td><span class="metric-pill hot"><?= number_format($sold) ?></span></td>
                    <td class="text-sm"><?= $catTotal[$cat] ?></td>
                    <td><span class="badge <?= $r<=2?'badge-danger':'badge-warning' ?>"><?= $r<=2?'Bán chạy':'Ổn định' ?></span></td>
                </tr>
            <?php $r++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Radar search -->
<div class="card" style="margin-bottom:20px;border-left:4px solid #22c55e">
    <div class="card-title" style="font-size:15px">Phân tích ngành moi</div>
    <p class="text-muted text-sm" style="margin-bottom:14px">Nhập từ khóa/ngành để crawl mau tu Shopee roi cham diem nhu cau.</p>
    <form data-ajax method="POST" action="<?= url('/scraper/radar') ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0">
            <label class="form-label">Ngành / Từ khóa</label>
            <input class="form-control" name="keyword" placeholder="VD: do hoc tap, setup ban hoc...">
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <label style="display:flex;align-items:center;gap:6px;color:var(--text-sec);font-size:13px;cursor:pointer">
                <input type="checkbox" name="fresh_crawl" value="1" style="accent-color:var(--accent)">
                Crawl mẫu từ Shopee
            </label>
            <button type="submit" class="btn btn-primary">Phân tích</button>
        </div>
    </form>
</div>

<!-- Tools -->
<div class="card" id="tools" style="margin-bottom:20px">
    <div class="card-title" style="font-size:15px">Công cụ thực tế</div>
    <p class="text-muted text-sm" style="margin-bottom:16px">Dùng trực tiếp các công cụ de phân tích thị trường, cai thien crawler va Dashboard.</p>
    <div class="tools-grid">
        <a class="tool-card" href="https://github.com/dtungpka/shopee-scraper" target="_blank">
            <div class="tool-name">dtungpka/shopee-scraper</div>
            <div class="tool-desc">Lay product + review Shopee bang Python Selenium/Playwright.</div>
            <span class="tool-tag">Python Scraping</span>
        </a>
        <a class="tool-card" href="https://github.com/AvazAsgarov/streamlit-e-commerce-dashboard" target="_blank">
            <div class="tool-name">streamlit-e-commerce-dashboard</div>
            <div class="tool-desc">Dashboard phân tích sales data bang Streamlit + Pandas.</div>
            <span class="tool-tag">Python Dashboard</span>
        </a>
        <a class="tool-card" href="https://github.com/GbollyAnaltic/ecommerce-dashboard" target="_blank">
            <div class="tool-name">ecommerce-dashboard</div>
            <div class="tool-desc">Interactive realtime dashboard bang Streamlit.</div>
            <span class="tool-tag">Streamlit Analytics</span>
        </a>
        <a class="tool-card" href="https://kameleo.io/blog/shopee-scraper-toolkit" target="_blank">
            <div class="tool-name">Kameleo Shopee Guide</div>
            <div class="tool-desc">Huong dan scrape Shopee voi anti-detect browser.</div>
            <span class="tool-tag">Anti-detect Guide</span>
        </a>
        <a class="tool-card" href="https://github.com/CloakHQ/CloakBrowser" target="_blank">
            <div class="tool-name">CloakBrowser</div>
            <div class="tool-desc">Chromium stealth hien dung trong he thong, tranh bi phat hien.</div>
            <span class="tool-tag">Node.js Anti-detect</span>
        </a>
        <a class="tool-card" href="<?= url('/settings/integrations') ?>">
            <div class="tool-name">Cấu hình tich hop</div>
            <div class="tool-desc">Quản lý API keys Kling, MeiGen, Facebook, Telegram.</div>
            <span class="tool-tag">Settings</span>
        </a>
        <a class="tool-card" href="https://grafana.com/docs/grafana/latest/dashboards/" target="_blank">
            <div class="tool-name">Grafana Dashboards</div>
            <div class="tool-desc">Xem bieu do truc tiep. Khởi động: <code>docker compose -f docker-compose.analytics.yml up -d</code></div>
            <span class="tool-tag">Analytics</span>
        </a>
        <a class="tool-card" href="https://github.com/topics/ecommerce-analysis" target="_blank">
            <div class="tool-name">GitHub Topics: ecommerce-analysis</div>
            <div class="tool-desc">Tim them repo phân tích du lieu thị trường tren GitHub.</div>
            <span class="tool-tag">GitHub Topics</span>
        </a>
    </div>
</div>

<!-- Grafana info -->
<div class="card" style="margin-bottom:20px;border-left:4px solid #8b5cf6">
    <div class="card-title" style="font-size:15px">Grafana Dashboard</div>
    <p class="text-muted text-sm" style="margin-bottom:12px">Khởi động Grafana roi truy cap bieu do truc tuyen:</p>
    <pre>docker compose -f docker-compose.analytics.yml up -d
# Truy cập: http://127.0.0.1:3000</pre>
    <p class="text-sm text-muted" style="margin-bottom:8px">Query mau Grafana - Top products by run-rate 7 ngay:</p>
    <pre>SELECT p.product_name,
  MAX(s.sold_count)-MIN(s.sold_count) AS sold_delta_7d,
  ROUND((MAX(s.sold_count)-MIN(s.sold_count))/GREATEST(1,TIMESTAMPDIFF(DAY,MIN(s.captured_at),MAX(s.captured_at))),2) AS run_rate_per_day
FROM product_market_snapshots s
JOIN affiliate_products p ON p.id=s.product_id AND p.site_id=s.site_id
WHERE s.site_id=1 AND s.captured_at>=NOW()-INTERVAL 7 DAY
GROUP BY p.id ORDER BY run_rate_per_day DESC LIMIT 20</pre>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;

    // Top sold horizontal bar
    new Chart(document.getElementById('topSoldChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{ label: 'Lượt bán', data: <?= json_encode($soldData) ?>, backgroundColor: 'rgba(34,197,94,0.7)', borderColor: '#22c55e', borderWidth: 1, borderRadius: 4 }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ' ' + Number(ctx.raw).toLocaleString() + ' da ban'; } } } }, scales: { x: { grid: { color: 'rgba(148,163,184,0.1)' } }, y: { grid: { display: false } } } }
    });

    // Price vs sold scatter
    var scatterData = <?= json_encode($scatterData) ?>;
    new Chart(document.getElementById('priceSoldChart'), {
        type: 'scatter',
        data: { datasets: [{ label: 'Sản phẩm', data: scatterData, backgroundColor: 'rgba(139,92,246,0.7)', pointRadius: 6 }] },
        options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { var d=ctx.raw; return ' Giá: '+Math.round(d[0]*1000).toLocaleString()+'d | Ban: '+d[1].toLocaleString(); } } } }, scales: { x: { title: { display: true, text: 'Giá (K VND)' }, grid: { color: 'rgba(148,163,184,0.1)' } }, y: { title: { display: true, text: 'Lượt bán' }, grid: { color: 'rgba(148,163,184,0.1)' } } } }
    });
});
</script>

</body>
</html>