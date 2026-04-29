# Implementation Tracker

## Tong quan

| Hang muc | Trang thai | Ghi chu |
|---|---|---|
| Scaffold project | Done | Da tao khung project rieng |
| Backend health endpoint | Done | `/backend/public/index.php` |
| Config bootstrap | Done | `backend/app/config/config.php` |
| Product sync module | Done | Dong bo JSON batch va luu local file |
| Product list API | Done | `GET /api/products` |
| Affiliate link module | Done | Tao deep link noi bo va cap nhat trang thai san pham |
| Affiliate link API | Done | `GET /api/links` |
| AI content module | Done | Sinh draft noi bo, approve/reject, cap nhat trang thai san pham |
| Content API | Done | `GET /api/contents` |
| OpenAI provider | Done | Ho tro `openai` va fallback `template_engine` |
| Posting module | Done | Schedule, mark posted, mark failed, cap nhat trang thai pipeline |
| Posting API | Done | `GET /api/posts` |
| Worker scripts | Done | Co script chay tung buoc va full pipeline |
| Cron sample | Done | `workers/cron.sample` |
| Site context | Done | Mac dinh `site_id = 1` cho single-laptop MVP |
| Fanpage API integration | Done | Ho tro `fanpage_api` va fallback `fanpage_manual` |

## Tuan hien tai

- Muc tieu: co flow dong bo san pham -> tao link -> tao draft -> duyet -> schedule dang bai tren laptop
- Da xong: dashboard MVP, local file storage, task logs, link generation, content draft, approve/reject, posting schedule, worker scripts, cron mau, site_id, OpenAI provider fallback, Fanpage API integration
- Tiep theo: bo sung env file, retry policy, va tach dashboard thanh cac route/view rieng
