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
    <a class="btn btn-primary" href="#charts">Biểu đồ</a>
    <a class="btn" href="#hot-cats">Danh mục Hot</a>
    <a class="btn btn-success" href="#radar-analysis">Phân tích</a>
</div>

<!-- KPI -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card accent"><div class="label">SP đã cào</div><div class="value"><?= number_format((int)($productSummary['total'] ?? 0)) ?></div></div>
    <div class="stat-card success"><div class="label">Cào từ scraper</div><div class="value"><?= number_format((int)($scraperSummary['total_scraped'] ?? 0)) ?></div></div>
    <div class="stat-card"><div class="label">Cấu hình</div><div class="value"><?= count($configs) ?></div></div>
    <div class="stat-card purple"><div class="label">Radar items</div><div class="value" id="stat-radar-count">—</div></div>
</div>

<!-- Session status badge (async) -->
<div id="session-status-bar" style="margin-bottom:14px;padding:10px 14px;border-radius:8px;background:var(--bg-elevated);border:1px solid var(--border);font-size:13px;display:flex;align-items:center;gap:10px">
    <span id="session-dot" style="width:10px;height:10px;border-radius:50%;background:#94a3b8;display:inline-block"></span>
    <span id="session-msg" style="color:var(--text-sec)">Đang kiểm tra kết nối Chrome Shopee...</span>
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

<!-- Radar results (async) -->
<div id="radar-results-wrap" class="card" style="margin-bottom:20px">
    <div class="card-title" style="font-size:15px">Kết quả phân tích Radar <span id="radar-loading-badge" class="badge" style="font-size:11px;margin-left:8px">⏳ Đang tải...</span></div>
    <div id="radar-results-body" style="padding:20px 0;text-align:center;color:var(--text-muted);font-size:13px">
        Đang tải dữ liệu Radar, vui lòng chờ...
    </div>
</div>

<!-- Hot categories -->
<div class="card" id="hot-cats" style="margin-bottom:20px">
    <div class="card-title" style="font-size:15px">Hot Categories - Top bán theo ngành</div>
    <p class="text-muted text-sm" style="margin-bottom:12px">Sản phẩm xếp theo tổng lượt bán. Những ngành có bán nhiều nhất.</p>
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
            <thead><tr><th>Ngành</th><th>Tổng lượt bán</th><th>Số SP</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php $r=1; foreach ($catGroups as $cat => $sold): if($sold==0) continue; ?>
                <tr>
                    <td data-label="Ngành"><strong><?= e($cat) ?></strong></td>
                    <td data-label="Tổng lượt bán"><span class="metric-pill hot"><?= number_format($sold) ?></span></td>
                    <td data-label="Số SP" class="text-sm"><?= $catTotal[$cat] ?></td>
                    <td data-label="Trạng thái"><span class="badge <?= $r<=2?'badge-danger':'badge-warning' ?>"><?= $r<=2?'Bán chạy':'Ổn định' ?></span></td>
                </tr>
            <?php $r++; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Radar search -->
<div class="card" id="radar-analysis" style="margin-bottom:20px;border-left:4px solid #3b82f6">
    <div class="card-title" style="font-size:15px">Phân tích AI Radar</div>
    <p class="text-muted text-sm" style="margin-bottom:14px">Sử dụng AI để tìm kiếm và phân tích cơ hội từ các sản phẩm liên quan.</p>
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

<div class="card" style="margin-bottom:20px;border-left:4px solid #22c55e">
    <div class="card-title" style="font-size:15px">Thu thập từ URL bất kỳ</div>
    <p class="text-muted text-sm" style="margin-bottom:14px">Dán URL từ bất kỳ nguồn nào — AI tự nhận diện và trích xuất sản phẩm.</p>
    <form data-ajax method="POST" action="<?= url('/scraper/scrape-url') ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="form-group" style="flex:2;min-width:240px;margin-bottom:0">
            <label class="form-label">URL nguồn</label>
            <input class="form-control" name="url" placeholder="https://... (Shopee, Tiki, Lazada, hoặc bất kỳ trang nào)">
        </div>
        <div class="form-group" style="flex:1;min-width:120px;margin-bottom:0">
            <label class="form-label">Giới hạn</label>
            <input class="form-control" name="limit" type="number" value="100" min="10" max="200">
        </div>
        <div style="margin-bottom:0">
            <button type="submit" class="btn btn-primary">Thu thập</button>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom:20px;border-left:4px solid #8b5cf6">
    <div class="card-title" style="font-size:15px">Dán dữ liệu thô</div>
    <p class="text-muted text-sm" style="margin-bottom:14px">Dán text, CSV, HTML table — AI tự trích xuất sản phẩm và lưu vào DB.</p>
    <form data-ajax method="POST" action="<?= url('/scraper/parse-raw') ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">
        <div class="form-group" style="flex:1;min-width:280px;margin-bottom:0">
            <label class="form-label">Dữ liệu thô (text/CSV/HTML)</label>
            <textarea class="form-control" name="raw" rows="5" placeholder="Dán dữ liệu sản phẩm ở đây..."></textarea>
        </div>
        <div class="form-group" style="flex:0;margin-bottom:0">
            <label class="form-label">Nguồn</label>
            <input class="form-control" name="platform" value="manual" placeholder="VD: shopee, tiki, manual">
        </div>
        <div style="flex:0;margin-top:24px">
            <button type="submit" class="btn btn-primary">Trích xuất</button>
        </div>
    </form>
</div>

<!-- ── Danh sách sản phẩm đã cào (affiliate_products) ── -->
<?php
$scrapedProducts = $scrapedProducts ?? [];
$spPagination = $scrapedPagination ?? ['page' => 1, 'totalPages' => 1, 'total' => 0];
$csrfToken = csrf_token();
?>
<div class="card" id="scraped-list" style="margin-bottom:20px">
    <div class="section-heading" style="margin-bottom:14px">
        <div>
            <div class="card-title">🗂️ Kho sản phẩm đã cào (<?= number_format((int)$spPagination['total']) ?> SP)</div>
            <div class="sub" style="font-size:12px">Chọn để chuyển sang My Products và thêm link affiliate</div>
        </div>
    </div>

    <?php if (empty($scrapedProducts)): ?>
        <div class="empty-state"><p>Chưa có sản phẩm nào. Chạy scraper để cào dữ liệu.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-main table-compact" id="scraped-products-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th style="width:70px">Nguồn</th>
                    <th style="width:95px">Giá</th>
                    <th style="width:75px">Đã bán</th>
                    <th style="width:80px">Link Aff</th>
                    <th style="width:80px">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($scrapedProducts as $sp): ?>
                <tr id="scraped-row-<?= (int)$sp['id'] ?>">
                    <td data-label="Sản phẩm">
                        <strong style="font-size:13px"><?= e(mb_substr($sp['product_name'], 0, 55)) ?></strong>
                        <?php if (!empty($sp['product_url'])): ?>
                            <a href="<?= e($sp['product_url']) ?>" target="_blank" rel="noreferrer" style="font-size:11px;color:var(--accent);display:block;margin-top:2px">Mở ↗</a>
                        <?php endif; ?>
                    </td>
                    <td data-label="Nguồn"><span class="badge badge-<?= e($sp['source_platform']) ?>" style="font-size:11px"><?= e($sp['source_platform']) ?></span></td>
                    <td data-label="Giá" class="text-sm"><?= (float)($sp['price'] ?? 0) > 0 ? number_format((float)$sp['price'], 0, ',', '.') . ' ₫' : '—' ?></td>
                    <td data-label="Đã bán"><span class="metric-pill <?= (int)($sp['sold_count'] ?? 0) >= 1000 ? 'hot' : '' ?>" style="font-size:11px"><?= number_format((int)($sp['sold_count'] ?? 0)) ?></span></td>
                    <td data-label="Link Aff" style="font-size:11px"><?= !empty($sp['affiliate_url']) ? '<span style="color:#22c55e">✓ Có</span>' : '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <td data-label="Thao tác">
                        <?php if (!empty($sp['usp_id'])): ?>
                            <button
                                type="button"
                                class="btn btn-success btn-sm"
                                disabled
                                style="font-size:11px;padding:4px 10px;display:block;width:100%;opacity:0.7;cursor:default"
                            >✓ Đã chọn</button>
                        <?php else: ?>
                            <button
                                type="button"
                                class="btn btn-accent btn-sm btn-select-scraped"
                                data-id="<?= (int)$sp['id'] ?>"
                                data-name="<?= e(mb_substr($sp['product_name'], 0, 50)) ?>"
                                style="font-size:11px;padding:4px 10px;display:block;width:100%"
                            >+ Chọn</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($spPagination['totalPages'] > 1): ?>
    <div style="display:flex;justify-content:center;gap:6px;margin-top:14px;flex-wrap:wrap">
        <?php if ($spPagination['page'] > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('/scraper?sp_page=' . ($spPagination['page'] - 1) . '#scraped-list') ?>">← Trang trước</a>
        <?php endif; ?>
        <?php for ($pg = max(1, $spPagination['page'] - 2); $pg <= min($spPagination['totalPages'], $spPagination['page'] + 2); $pg++): ?>
            <?php if ($pg == $spPagination['page']): ?>
                <span class="btn btn-accent btn-sm"><?= $pg ?></span>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= url('/scraper?sp_page=' . $pg . '#scraped-list') ?>"><?= $pg ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($spPagination['page'] < $spPagination['totalPages']): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('/scraper?sp_page=' . ($spPagination['page'] + 1) . '#scraped-list') ?>">Trang sau →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const BASE_URL = '<?= rtrim(url(''), '/') ?>';
const CSRF_TOKEN = '<?= e($csrfToken) ?>';

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

    // ── Async: kiểm tra trạng thái session Chrome Shopee ──
    fetch(BASE_URL + '/api/scraper/status')
        .then(r => r.json())
        .then(res => {
            const dot = document.getElementById('session-dot');
            const msg = document.getElementById('session-msg');
            if (!res.success) {
                dot.style.background = '#ef4444';
                msg.textContent = 'Lỗi kiểm tra Chrome: ' + (res.message || 'Unknown');
                return;
            }
            const d = res.data || {};
            if (d.alive) {
                dot.style.background = d.captcha_required ? '#f59e0b' : '#22c55e';
                msg.textContent = d.message || 'Chrome đang chạy';
            } else {
                dot.style.background = '#94a3b8';
                msg.textContent = d.message || 'Không tìm thấy Chrome — scraper sẽ tự mở khi cào.';
            }
        })
        .catch(() => {
            document.getElementById('session-msg').textContent = 'Không lấy được trạng thái Chrome.';
        });

    // ── Async: tải dữ liệu Radar ──
    fetch(BASE_URL + '/api/scraper/radar?limit=12')
        .then(r => r.json())
        .then(res => {
            const badge = document.getElementById('radar-loading-badge');
            const body  = document.getElementById('radar-results-body');
            const stat  = document.getElementById('stat-radar-count');
            if (!res.success || !res.data) {
                badge.textContent = '⚠️ Lỗi';
                body.innerHTML = '<p style="color:#ef4444;padding:12px">' + (res.message || 'Lỗi tải Radar.') + '</p>';
                return;
            }
            const radar = res.data;
            stat.textContent = radar.count || 0;
            badge.textContent = '✅ ' + (radar.count || 0) + ' cơ hội';
            if (!radar.opportunities || !radar.opportunities.length) {
                body.innerHTML = '<p style="padding:12px;color:var(--text-muted)">Chưa có dữ liệu phân tích. Cào thêm sản phẩm và chạy lại.</p>';
                return;
            }
            const rows = radar.opportunities.slice(0, 8).map(item => {
                const score = item.score || 0;
                const color = score >= 75 ? '#22c55e' : (score >= 55 ? '#f59e0b' : '#ef4444');
                return `<tr>
                    <td><span class="metric-pill" style="background:${color}20;color:${color};font-weight:700">${score}</span></td>
                    <td><strong class="text-sm">${escHtml(item.name || '').slice(0, 45)}</strong></td>
                    <td class="text-sm">${escHtml(item.demand || '')}</td>
                    <td class="text-sm">${escHtml((item.content_angle || '').slice(0, 35))}</td>
                </tr>`;
            }).join('');
            body.innerHTML = `<div class="table-wrap"><table class="table-main table-compact">
                <thead><tr><th style="width:55px">Điểm</th><th>Sản phẩm</th><th>Nhu cầu</th><th>Góc content</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`;
        })
        .catch(err => {
            document.getElementById('radar-loading-badge').textContent = '⚠️ Lỗi';
            document.getElementById('radar-results-body').innerHTML = '<p style="color:#ef4444;padding:12px">Không tải được Radar.</p>';
        });

    // ── Chọn sản phẩm đã cào → My Products ──
    document.querySelectorAll('.btn-select-scraped').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id   = btn.dataset.id;
            const name = btn.dataset.name;
            btn.disabled = true;
            btn.textContent = '...';

            const fd = new FormData();
            fd.append('product_id', id);
            fd.append('csrf_token', CSRF_TOKEN);

            fetch(BASE_URL + '/products/select-scraped', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const parent = btn.parentNode;
                        parent.innerHTML = `
                            <button
                                type="button"
                                class="btn btn-success btn-sm"
                                disabled
                                style="font-size:11px;padding:4px 10px;display:block;width:100%;opacity:0.7;cursor:default"
                            >✓ Đã chọn</button>
                        `;
                    } else {
                        btn.disabled = false;
                        btn.textContent = '+ Chọn';
                        alert('Lỗi: ' + (res.message || 'Không xác định'));
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = '+ Chọn';
                    alert('Lỗi kết nối, vui lòng thử lại.');
                });
        });
    });

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
});
</script>

</body>
</html>