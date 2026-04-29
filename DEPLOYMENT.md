# Cài đặt & chạy Affiliate MVP Laptop trên server

## Yêu cầu

- PHP 8.3+ CLI/Web server
- Không cần Composer/npm ở bản scaffold hiện tại
- Data JSON lưu tại `storage/data`
- Log lưu tại `storage/logs`

## Chạy dashboard

```bash
cd /home/dokien/.openclaw/workspace/MMO
./scripts/start-server.sh
```

Mặc định chạy tại:

- Local: `http://127.0.0.1:8088/`
- LAN: `http://192.168.1.33:8088/`
- Tailscale: `http://100.84.215.4:8088/`

Health check:

```bash
curl http://127.0.0.1:8088/health
```

Dừng server:

```bash
./scripts/stop-server.sh
```

## Biến môi trường tùy chọn

Xem `.env.example`. Bản hiện tại đọc trực tiếp từ environment:

```bash
export OPENAI_API_KEY="..."
export OPENAI_MODEL="gpt-4o-mini"
export FACEBOOK_PAGE_ID="..."
export FACEBOOK_PAGE_ACCESS_TOKEN="..."
```

Nếu không có OpenAI key, hệ thống fallback về `template_engine`.
Nếu không có Facebook token/page id, dùng channel `fanpage_manual` và mark posted thủ công trên dashboard.

## Worker pipeline

```bash
php workers/run_pipeline.php affiliate_api storage/data/sources/sample_products.json MVP-LAPTOP template_engine fanpage_manual 10
```

Các API kiểm tra nhanh:

- `/health`
- `/api/products`
- `/api/links`
- `/api/contents`
- `/api/posts`

## Cron mẫu

Xem `workers/cron.sample`. Chưa tự động cài crontab để tránh chạy job ngoài ý muốn.


## Database thật

Dự án hiện chạy bằng MariaDB/MySQL, không ghi dữ liệu runtime vào `storage/data/*.json` nữa.

- Database: `mmo_affiliate`
- User app: `mmo_app`
- Credential local: `backend/app/config/local.php` (đã bị `.gitignore`, không push lên git)

Import dữ liệu JSON cũ vào DB:

```bash
php scripts/migrate-json-to-db.php
```

Service production local:

```bash
systemctl status mariadb
systemctl status mmo-app
```

`mmo-app` đã được cấu hình phụ thuộc `mariadb.service` để reboot xong DB chạy trước app.
