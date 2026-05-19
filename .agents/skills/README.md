# MMO Content & Prompt Skills

Tập hợp skill viết content và prompt cho hệ thống MMO. Các skill được cài từ ClawHub và symlink vào `.agents/skills/`.

## 📝 Content Writing Skills

### `copywriting` (score: 4.4 ⭐)
Viết copy chuyển đổi cao theo framework **AIDA, PAS, FAB**. Trigger: headline, CTA, product description, ad copy, email sequence.

### `article-writing` (score: 4.2 ⭐)
Viết bài dài: blog post, tutorials, newsletter, case study. Dùng khi cần nội dung dài hơn 1 đoạn, có cấu trúc rõ ràng.

### `content-generation` (score: 4.3 ⭐)
Tạo content đa format: articles, reports, social posts, marketing copy. Tổng hợp cho mọi loại content.

### `social-media-content` (score: 4.1 ⭐)
Tạo post cho Facebook, TikTok, Instagram, YouTube. Tối ưu caption + hashtags theo platform.

### `ai-social-media-content` (score: 3.1)
AI-powered content cho TikTok/Reels/Shorts. Generate images, videos, thumbnails, captions.

### `product-description-generator` (score: 3.1)
Viết mô tả sản phẩm chuẩn SEO cho Amazon, Shopee, Shopify, eBay. Tối ưu conversion-focused copy.

### `content-writing-thought-leadership` (score: 3.2)
B2B content writing, chiến lược nội dung dài hạn, batch workflow.

### `ai-writing-agent` (score: 1.1)
AI writing assistant tổng hợp — articles, blog posts, marketing content có cấu trúc.

### `seo-article-generator` (score: 2.9)
Tạo bài viết chuẩn SEO: article brief, keyword cluster, content structure.

### `jx-marketing` (score: 2.9)
Extract thông tin từ product URL → Instagram marketing content (caption, hashtags, image ideas).

## ⚙️ Prompt Engineering Skills

### `prompt-engineering` (score: 4.2 ⭐)
Master prompting: chain-of-thought, few-shot, system prompts, negative prompts. Models: Claude, GPT-4, Gemini, FLUX, Veo, Stable Diffusion.

### `seo-keyword-researcher` (score: 3.0)
Research từ khóa SEO: primary/secondary keywords, competition analysis, article outline, SEO recommendations.

## 🔗 Affiliate & Operations Skills

### `affiliate-link-injector` (score: 3.0)
Quét content → tự động chèn affiliate links + FTC compliance disclosures.

### `web-scraping` (score: 4.3 ⭐)
Cào dữ liệu web: trang thương mại điện tử, trích xuất structured data, social listening.

### `social-media-scheduler` (score: n/a)
Lên lịch đăng content, content calendar, platform-optimized posting.

### `ai-marketing-automation` (score: n/a)
Tự động hóa marketing bằng AI agent.

## 🎯 Cách dùng trong MMO

Khi tạo content cho sản phẩm:
1. **Nếu cần viết copy** → trigger `copywriting` với AIDA/PAS framework
2. **Nếu cần bài dài** → trigger `article-writing` 
3. **Nếu cần mô tả sản phẩm** → trigger `product-description-generator`
4. **Nếu cần content social** → trigger `social-media-content` hoặc `ai-social-media-content`
5. **Nếu cần SEO** → trigger `seo-keyword-researcher` → `seo-article-generator`
6. **Nếu cần prompt tối ưu** → trigger `prompt-engineering`

## 📁 Vị trí Skill

```
MMO/.agents/skills/          ← symlink đến ~/.openclaw/skills/skills/
├── copywriting/
├── article-writing/
├── content-generation/
├── social-media-content/
├── product-description-generator/
├── prompt-engineering/
├── seo-keyword-researcher/
├── seo-article-generator/
├── ai-social-media-content/
├── ai-writing-agent/
├── content-writing-thought-leadership/
├── jx-marketing/
├── affiliate-link-injector/
├── web-scraping/
├── social-media-scheduler/
└── ai-marketing-automation/
```

## 🔧 Tích hợp vào ContentService

Khi gọi ContentService để tạo draft:
- System prompt đã dùng PromptTemplateService → có thể reference copywriting framework
- User prompt dùng product data → có thể áp dụng `product-description-generator` output
- Media prompt cho ảnh → có thể reference `prompt-engineering` technique

## 💡 Example Prompts

### AIDA Copywriting
```
Viết copy theo framework AIDA cho sản phẩm: {{product_name}}
- Attention: gây chú ý bằng headline gây sốc
- Interest: tạo tò mò với insight độc đáo
- Desire: khiến người đọc MUỐN sản phẩm
- Action: kêu gọi hành động ngay
```

### Product Description (SEO)
```
Tạo mô tả sản phẩm chuẩn SEO cho {{product_name}}:
- Platform: {{platform}}
- Features & Benefits
- Keywords: {{keywords}}
- CTA compelling
- 150-300 từ
```