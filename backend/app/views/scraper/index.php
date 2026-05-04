<div class="page-header">
    <div>
        <h2>🕷️ Cào dữ liệu sản phẩm</h2>
        <p>Tự động tìm sản phẩm bán chạy từ Shopee, TikTok Shop, Lazada</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card accent"><div class="label">Cấu hình</div><div class="value"><?= (int)$scraperSummary['total_configs'] ?></div></div>
    <div class="stat-card success"><div class="label">Đang hoạt động</div><div class="value"><?= (int)$scraperSummary['active_configs'] ?></div></div>
    <div class="stat-card purple"><div class="label">Đã cào (tổng)</div><div class="value"><?= number_format((int)$scraperSummary['total_scraped']) ?></div></div>
    <div class="stat-card"><div class="label">SP bán chạy</div><div class="value"><?= (int)$productSummary['high_demand'] ?></div></div>
</div>

<!-- ═══ TRENDING 1 CLICK — Không cần từ khóa ═══ -->
<div class="card" style="margin-bottom:24px;border-image:linear-gradient(135deg, #06b6d4, #8b5cf6) 1;border-width:1px 1px 1px 3px;">
    <div class="card-title" style="font-size:16px">🔥 Cào Top Bán Chạy — Không cần từ khóa</div>
    <p class="text-muted text-sm" style="margin-bottom:16px">Tự động quét sản phẩm có lượt mua cao nhất trên Shopee theo từng danh mục. Chỉ cần chọn danh mục và bấm chạy.</p>

    <form data-ajax method="POST" action="<?= url('/scraper/trending') ?>">
        <div class="grid-12" style="margin-bottom:0">
            <div>
                <div class="form-group">
                    <label class="form-label">Sàn TMĐT</label>
                    <select class="form-control" name="platform">
                        <option value="tiki">Tiki (ổn định nhất)</option>
                        <option value="shopee">Shopee</option>
                        <option value="tiktokshop">TikTok Shop</option>
                        <option value="lazada">Lazada</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Lượt mua tối thiểu</label>
                    <input class="form-control" name="min_sold_count" type="number" min="0" value="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Số trang / danh mục (1–3)</label>
                    <input class="form-control" name="max_pages" type="number" min="1" max="3" value="2">
                </div>
            </div>
            <div>
                <div class="form-group">
                    <label class="form-label">Chọn danh mục (bỏ trống = tất cả)</label>
                    <div style="max-height:200px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px 10px;">
                        <?php foreach ($categories as $catId => $catName): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:13px;color:var(--text-sec);">
                            <input type="checkbox" name="category_ids[]" value="<?= $catId ?>" style="accent-color:var(--accent);">
                            <?= e($catName) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-top:12px;font-size:14px;padding:12px;">
            🚀 Cào Top Bán Chạy ngay
        </button>
    </form>
</div>

<div class="grid-12">
    <!-- Add Config Form -->
    <div class="card">
        <div class="card-title">➕ Thêm cấu hình cào theo từ khóa</div>
        <p class="text-muted text-sm" style="margin-bottom:16px">Nếu muốn cào theo từ khóa cụ thể. Để trống = chế độ trending tự động.</p>
        <form data-ajax method="POST" action="<?= url('/scraper/save-config') ?>">
            <div class="form-group">
                <label class="form-label">Từ khóa (để trống = trending)</label>
                <input class="form-control" name="keyword" placeholder="vd: laptop gaming, tai nghe bluetooth... hoặc để trống">
            </div>
            <div class="form-group">
                <label class="form-label">Sàn TMĐT</label>
                <select class="form-control" name="platform">
                    <option value="shopee">Shopee</option>
                    <option value="tiki">Tiki</option>
                    <option value="tiktokshop">TikTok Shop</option>
                    <option value="lazada">Lazada</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Lượt mua tối thiểu</label>
                <input class="form-control" name="min_sold_count" type="number" min="0" value="100">
            </div>
            <div class="form-group">
                <label class="form-label">Số trang cào (1–10)</label>
                <input class="form-control" name="max_pages" type="number" min="1" max="10" value="3">
            </div>
            <div class="form-group">
                <label class="form-label">Sắp xếp theo</label>
                <select class="form-control" name="sort_by">
                    <option value="sold">Bán chạy nhất</option>
                    <option value="relevance">Liên quan nhất</option>
                    <option value="price_asc">Giá tăng dần</option>
                    <option value="price_desc">Giá giảm dần</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Lưu cấu hình</button>
        </form>
    </div>

    <!-- Run Actions -->
    <div class="card">
        <div class="card-title">⚡ Chạy cào dữ liệu (configs)</div>
        <p class="text-muted text-sm" style="margin-bottom:16px">Chạy tất cả cấu hình đang hoạt động. Quá trình có thể mất vài phút.</p>
        <form data-ajax method="POST" action="<?= url('/scraper/run-all') ?>">
            <button type="submit" class="btn btn-accent btn-full" style="margin-bottom:12px">
                ⚡ Chạy tất cả (<?= (int)$scraperSummary['active_configs'] ?> cấu hình)
            </button>
        </form>
        <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
        <div class="card-title" style="font-size:14px">Chạy riêng lẻ</div>
        <?php if (empty($configs)): ?>
            <p class="text-muted text-sm">Chưa có cấu hình nào.</p>
        <?php else: ?>
            <?php foreach ($configs as $config):
                $displayKw = $config['keyword'] === '__trending__' ? '🔥 TRENDING' : e($config['keyword']);
            ?>
                <div class="scraper-action-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:10px 12px;border-radius:8px;background:var(--bg-elevated);">
                    <div style="flex:1;min-width:0">
                        <strong class="text-sm"><?= $displayKw ?></strong>
                        <div class="text-xs text-muted"><?= e($config['platform']) ?> · ≥<?= number_format((int)$config['min_sold_count']) ?> sold</div>
                    </div>
                    <?= status_badge((int)$config['is_active'] ? 'active' : 'archived') ?>
                    <form data-ajax method="POST" action="<?= url('/scraper/run') ?>" style="margin:0">
                        <input type="hidden" name="config_id" value="<?= (int)$config['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">Chạy</button>
                    </form>
                    <form data-ajax method="POST" action="<?= url('/scraper/delete-config') ?>" style="margin:0">
                        <input type="hidden" name="config_id" value="<?= (int)$config['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:var(--danger);color:#fff;" data-confirm="Xóa cấu hình này?">Xóa</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Configs Table -->
<div class="card">
    <div class="card-title">📋 Danh sách cấu hình cào dữ liệu</div>
    <?php if (empty($configs)): ?>
        <div class="empty-state"><p>Chưa có cấu hình nào.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Từ khóa</th>
                        <th>Sàn</th>
                        <th>Min sold</th>
                        <th>Trang</th>
                        <th>Sắp xếp</th>
                        <th>Trạng thái</th>
                        <th>Lần chạy cuối</th>
                        <th>Kết quả</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($configs as $config):
                    $result = json_decode($config['last_run_result'] ?? '{}', true);
                    $sortLabels = ['sold' => 'Bán chạy', 'relevance' => 'Liên quan', 'price_asc' => 'Giá ↑', 'price_desc' => 'Giá ↓'];
                    $platformLabels = ['shopee' => 'Shopee', 'tiktokshop' => 'TikTok Shop', 'lazada' => 'Lazada', 'tiki' => 'Tiki'];
                ?>
                    <tr>
                        <td>#<?= (int)$config['id'] ?></td>
                        <td><strong><?= e($config['keyword']) ?></strong></td>
                        <td><span class="badge badge-<?= e($config['platform']) ?>"><?= $platformLabels[$config['platform']] ?? ucfirst($config['platform']) ?></span></td>
                        <td class="text-sm"><?= number_format((int)$config['min_sold_count']) ?></td>
                        <td class="text-sm"><?= (int)$config['max_pages'] ?></td>
                        <td class="text-sm"><?= $sortLabels[$config['sort_by']] ?? $config['sort_by'] ?></td>
                        <td><?= status_badge((int)$config['is_active'] ? 'active' : 'archived') ?></td>
                        <td class="text-sm"><?= $config['last_run_at'] ? date('d/m/Y H:i', strtotime($config['last_run_at'])) : '—' ?></td>
                        <td class="text-sm">
                            <?php if (!empty($result)): ?>
                                <span title="Scraped: <?= (int)($result['scraped'] ?? 0) ?>, Filtered: <?= (int)($result['filtered'] ?? 0) ?>">
                                    📦 <?= (int)($result['scraped'] ?? 0) ?> → ✅ <?= (int)($result['filtered'] ?? 0) ?> SP
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Top Selling Products from scraper -->
<?php if (!empty($topProducts)): ?>
<div class="card">
    <div class="card-title">🔥 Top sản phẩm bán chạy (đã cào)</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Sản phẩm</th><th>Nguồn</th><th>Giá</th><th>Lượt mua</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($topProducts as $p): ?>
                <tr>
                    <td>
                        <strong><?= e((string)$p['product_name']) ?></strong>
                        <div class="sub"><?= e((string)$p['source_product_id']) ?></div>
                        <a href="<?= e((string)$p['product_url']) ?>" target="_blank" rel="noreferrer" class="text-xs">Mở link ↗</a>
                    </td>
                    <td class="text-sm"><?= e((string)$p['source_platform']) ?></td>
                    <td class="text-sm"><?= number_format((float)($p['price'] ?? 0), 0, ',', '.') ?> ₫</td>
                    <td><span class="metric-pill hot"><?= number_format((int)($p['sold_count'] ?? 0)) ?></span></td>
                    <td><?= status_badge((string)$p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
