# Workers

Thu muc nay chua worker scripts cho pipeline MVP tren laptop.

## Scripts

- `sync_sample_products.php`
  - Dong bo san pham tu file JSON mau vao he thong.
- `scrape_tiki_source.php`
  - Cao san pham Tiki ra file JSON nguon, khong can MySQL.
- `../scripts/marketplace_playwright_scraper.py`
  - Cao Shopee/Lazada bang Playwright network interception, chay cham de uu tien co ket qua.
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
php workers/scrape_tiki_source.php "laptop" 2 40
python3 scripts/marketplace_playwright_scraper.py --platform shopee --keyword "laptop" --scrolls 10 --min-delay 5 --max-delay 10 --output storage/data/sources/shopee_laptop_products.json
python3 scripts/marketplace_playwright_scraper.py --platform lazada --keyword "laptop" --scrolls 10 --min-delay 5 --max-delay 10 --output storage/data/sources/lazada_laptop_products.json
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

## Playwright scraper cham

Lan dau cai dependency:

```bash
python3 -m pip install -r requirements-scraper.txt
python3 -m playwright install chromium
```

Neu chay tren Ubuntu server/terminal khong co GUI, cai Xvfb:

```bash
sudo apt-get install xvfb
```

Neu gap captcha, chay headful de giai bang tay. Script se luu metadata + screenshot captcha vao `storage/data/captcha` de ban xem nhanh:

```bash
xvfb-run -a python3 scripts/marketplace_playwright_scraper.py --platform shopee --keyword "laptop" --headed --warmup-session --pause-on-captcha --scrolls 10 --output storage/data/sources/shopee_laptop_products.json --raw-output storage/data/sources/shopee_laptop_raw.jsonl --captcha-artifact-dir storage/data/captcha
```

Shopee login assist co the tu dien tai khoan tu file `.env` cuc bo va luu cookie/session vao profile rieng. Neu Shopee hien captcha/xac minh, script chi chup artifact va dung cho ban giai tay, khong tu dong vuot captcha:

```bash
cp .env.example .env
# Dien SHOPEE_USERNAME va SHOPEE_PASSWORD trong .env
xvfb-run -a python3 scripts/marketplace_playwright_scraper.py --platform shopee --headed --shopee-login-assist --pause-on-captcha --user-data-dir storage/browser/shopee-profile-v2 --captcha-artifact-dir storage/data/captcha --scrolls 10 --output storage/data/sources/shopee_laptop_products.json
```

Shopee category ban chay co HTML fallback tu card san pham. Khi co DOM card, script se tu boc HTML sau khi cuon trang:

```bash
xvfb-run -a python3 scripts/marketplace_playwright_scraper.py --platform shopee --shopee-category-id 11035567 --shopee-category-name "Thời Trang Nam" --category-page 0 --headed --scrolls 8 --min-delay 4 --max-delay 9 --output storage/data/sources/shopee_thoi_trang_nam_products.json --raw-output storage/data/sources/shopee_thoi_trang_nam_raw.jsonl --captcha-artifact-dir storage/data/captcha
```

Neu output co `final_url` dang `/verify/captcha` hoac `html_cards: 0`, profile dang bi Shopee verify. Xvfb chi tao man hinh ao an, nen khong tien giai captcha neu khong co VNC. Cach chac hon la chay mot lan tren desktop/VNC voi cung `--user-data-dir`, dang nhap/giai captcha, nhan Enter, roi moi chay cron bang `xvfb-run`.

Neu dang chay tren desktop/VNC co GUI san, co the bo `xvfb-run -a`:

```bash
python3 scripts/marketplace_playwright_scraper.py --platform shopee --keyword "laptop" --headed --warmup-session --playwright-pause --pause-on-captcha --scrolls 10 --output storage/data/sources/shopee_laptop_products.json
```

Lazada can bat endpoint `mtop.lazada.search...`, nen nen luu raw de debug payload neu san doi format:

```bash
xvfb-run -a python3 scripts/marketplace_playwright_scraper.py --platform lazada --keyword "laptop" --headed --warmup-session --pause-on-captcha --scrolls 10 --output storage/data/sources/lazada_laptop_products.json --raw-output storage/data/sources/lazada_laptop_raw.jsonl
```

Sau khi co file JSON nguon va MySQL da bat, sync vao pipeline:

```bash
php workers/sync_sample_products.php shopee storage/data/sources/shopee_laptop_products.json
php workers/sync_sample_products.php lazada storage/data/sources/lazada_laptop_products.json
```


## Gemini content provider

Có thể chọn provider `gemini` hoặc `auto`. `auto` sẽ thử Gemini trước, sau đó OpenAI, cuối cùng fallback về template nội bộ nếu API lỗi hoặc thiếu key.
