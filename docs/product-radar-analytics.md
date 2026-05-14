# Product Radar Analytics

Mục tiêu: biến `/scraper` thành radar tìm sản phẩm hot/tiềm năng thay vì chỉ cào data thô.

## Kiến trúc nhẹ khuyến nghị cho server hiện tại

Server RAM thấp, nên bản đầu dùng:

1. **Scraper job**: `scripts/product_radar_daily.php`
   - Chạy theo cron mỗi ngày.
   - Quét 1 ngành hàng/ngày, tối đa 100 sản phẩm.
   - Dùng CloakBrowser slow crawl, delay dài, không tự vượt captcha.

2. **Database**: MariaDB hiện có
   - Không thêm PostgreSQL/InfluxDB vội để tránh nặng máy và trùng dữ liệu.
   - Bảng time-series: `product_market_snapshots`.
   - Mỗi snapshot lưu: `price`, `sold_count`, `review_count`, `rating`, `captured_at`.

3. **Dashboard**: Grafana OSS
   - Chạy bằng `docker-compose.analytics.yml`.
   - Kết nối trực tiếp vào MariaDB.
   - Vẽ run-rate, growth, biến động giá, review/sold.

## Chạy daily scraper thủ công

Dry-run:

```bash
php scripts/product_radar_daily.php --site=1 --category=11035567 --pages=1 --limit=100 --dry-run
```

Chạy thật:

```bash
php scripts/product_radar_daily.php --site=1 --category=11035567 --pages=1 --limit=100
```

Một số category đang có trong `ScraperService::SHOPEE_CATEGORIES`:

- `11035567` — Thời trang nam
- `11035639` — Thời trang nữ
- `11036670` — Nhà cửa & Đời sống
- `11036279` — Sức khỏe & Sắc đẹp
- `11036382` — Mẹ & Bé
- `11036101` — Thiết bị điện gia dụng

## Cài cron

Xem mẫu:

```bash
cat workers/product-radar-daily.cron.sample
```

Cài vào crontab:

```bash
crontab workers/product-radar-daily.cron.sample
```

Log:

```bash
tail -f storage/logs/product-radar-daily.log
```

## Chạy Grafana

Tạo env riêng, không commit secret:

```bash
cp .env.analytics.example .env.analytics
nano .env.analytics
```

Start:

```bash
docker compose --env-file .env.analytics -f docker-compose.analytics.yml up -d
```

Mở:

```text
http://127.0.0.1:3000
```

Nếu muốn public qua Cloudflare Tunnel, tạo hostname riêng sau, ví dụ `grafana.sys-erp.id.vn`, và đặt mật khẩu mạnh.

## Query Grafana mẫu

### Run-rate theo ngày

```sql
SELECT
  captured_at AS time,
  product_id,
  sold_count
FROM product_market_snapshots
WHERE $__timeFilter(captured_at)
  AND site_id = 1
ORDER BY captured_at
```

### Biến động giá

```sql
SELECT
  captured_at AS time,
  product_id,
  price
FROM product_market_snapshots
WHERE $__timeFilter(captured_at)
  AND site_id = 1
ORDER BY captured_at
```

### Top sản phẩm tăng nhanh trong 7 ngày

```sql
SELECT
  p.product_name,
  MAX(s.sold_count) - MIN(s.sold_count) AS sold_delta_7d,
  ROUND((MAX(s.sold_count) - MIN(s.sold_count)) / GREATEST(1, TIMESTAMPDIFF(DAY, MIN(s.captured_at), MAX(s.captured_at))), 2) AS run_rate_per_day,
  MIN(s.price) AS min_price,
  MAX(s.price) AS max_price
FROM product_market_snapshots s
JOIN affiliate_products p ON p.id = s.product_id AND p.site_id = s.site_id
WHERE s.site_id = 1
  AND s.captured_at >= NOW() - INTERVAL 7 DAY
GROUP BY p.id, p.product_name
ORDER BY run_rate_per_day DESC
LIMIT 20
```

### Cảnh báo review/sold thấp

Hiện `review_count` chưa được lấy đầy đủ ở crawler UI. Khi bổ sung crawl review_count, dùng query:

```sql
SELECT
  p.product_name,
  MAX(s.sold_count) AS sold_count,
  MAX(s.review_count) AS review_count,
  ROUND(MAX(s.review_count) / GREATEST(1, MAX(s.sold_count)) * 100, 2) AS review_ratio_pct
FROM product_market_snapshots s
JOIN affiliate_products p ON p.id = s.product_id AND p.site_id = s.site_id
WHERE s.site_id = 1
GROUP BY p.id, p.product_name
HAVING sold_count >= 1000 AND review_ratio_pct < 3
ORDER BY sold_count DESC
```

## Khi nào cần PostgreSQL/InfluxDB?

- Dùng MariaDB hiện tại nếu chỉ vài chục nghìn snapshot/ngày.
- Chuyển sang InfluxDB khi cần lưu time-series lớn, retention/downsampling, nhiều dashboard realtime.
- Chuyển sang PostgreSQL khi cần analytics SQL mạnh hơn, materialized views, full-text/trigram tốt hơn.

Bản hiện tại ưu tiên chạy nhẹ, dễ vận hành, không làm server quá tải.
