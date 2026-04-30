# Workers

Thu muc nay chua worker scripts cho pipeline MVP tren laptop.

## Scripts

- `sync_sample_products.php`
  - Dong bo san pham tu file JSON mau vao he thong.
- `generate_links.php`
  - Tao affiliate link noi bo cho san pham `new`.
- `generate_contents.php`
  - Sinh draft content cho san pham da `linked`.
  - Ho tro provider `template_engine` va `openai`.
- `schedule_posts.php`
  - Tao lich dang bai cho content `approved`.
- `publish_scheduled_posts.php`
  - Publish cac bai `scheduled` co channel `fanpage_api`.
- `run_pipeline.php`
  - Chay toan bo pipeline theo thu tu: sync -> link -> content -> approve draft -> schedule.

## Cach chay tay

```bash
php workers/sync_sample_products.php
php workers/generate_links.php MVP-LAPTOP 10
php workers/generate_contents.php template_engine 10
php workers/generate_contents.php openai 10
php workers/schedule_posts.php fanpage_manual 10
php workers/publish_scheduled_posts.php 10
php workers/run_pipeline.php affiliate_api storage/data/sources/sample_products.json MVP-LAPTOP template_engine fanpage_manual 10
```

## OpenAI

Dat bien moi truong truoc khi chay:

```bash
export OPENAI_API_KEY="your_api_key"
export OPENAI_MODEL="gpt-4o-mini"
export GEMINI_API_KEY="..."
export GEMINI_MODEL="gemini-1.5-flash"
```

Neu `OPENAI_API_KEY` khong co hoac OpenAI loi, he thong tu dong fallback ve `template_engine`.

## Fanpage API

Dat bien moi truong truoc khi publish that:

```bash
export FACEBOOK_PAGE_ID="your_page_id"
export FACEBOOK_PAGE_ACCESS_TOKEN="your_page_access_token"
```

Neu chua co token/page id, hay dung channel `fanpage_manual` va thao tac `Mark posted` tren dashboard.

## Cron

Xem file `workers/cron.sample` de gan vao `crontab -e` tren laptop.


## Gemini content provider

Có thể chọn provider `gemini` hoặc `auto`. `auto` sẽ thử Gemini trước, sau đó OpenAI, cuối cùng fallback về template nội bộ nếu API lỗi hoặc thiếu key.
