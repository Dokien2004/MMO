#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { launchPersistentContext } from 'cloakbrowser';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const randomInt = (min, max) => Math.floor(min + Math.random() * (max - min + 1));
const humanDelay = async (minMs, maxMs) => sleep(randomInt(minMs, maxMs));

function normalizeNumber(value) {
  const raw = String(value || '').trim().toLowerCase().replace(',', '.');
  if (!raw) return 0;
  const multiplier = raw.includes('k') ? 1000 : raw.includes('tr') ? 1000000 : 1;
  const number = parseFloat(raw.replace(/[^0-9.]/g, ''));
  return Number.isFinite(number) ? Math.round(number * multiplier) : 0;
}

function normalizePrice(value) {
  const raw = String(value || '').replace(/₫/g, '').trim();
  const first = raw.split('-')[0] || raw;
  const digits = first.replace(/[^0-9]/g, '');
  if (digits.length < 4) return 0;
  return Number(digits) || 0;
}

function parseProductId(url) {
  const decoded = decodeURIComponent(String(url || ''));
  let match = decoded.match(/-i\.(\d+)\.(\d+)/);
  if (!match) match = decoded.match(/\/product\/(\d+)\/(\d+)/);
  if (!match) return null;
  return { shopId: match[1], itemId: match[2] };
}

function buildUrl(job) {
  const page = Math.max(0, Number(job.page || 1) - 1);
  const sortBy = String(job.sortBy || 'sold');
  const params = new URLSearchParams();
  params.set('page', String(page));
  if (sortBy === 'sold') params.set('sortBy', 'sales');
  if (sortBy === 'price_asc') { params.set('sortBy', 'price'); params.set('order', 'asc'); }
  if (sortBy === 'price_desc') { params.set('sortBy', 'price'); params.set('order', 'desc'); }

  if (job.type === 'category') {
    params.set('category', String(job.categoryId || ''));
    return `https://shopee.vn/search?${params.toString()}`;
  }

  params.set('keyword', String(job.keyword || ''));
  return `https://shopee.vn/search?${params.toString()}`;
}

async function scrapeJob(context, job, options) {
  const page = await context.newPage();
  const url = buildUrl(job);
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await humanDelay(options.initialDelayMin, options.initialDelayMax);

    const state = await page.evaluate(() => {
      const text = document.body?.innerText || '';
      return {
        href: location.href,
        title: document.title,
        text: text.slice(0, 1200),
        htmlLength: document.documentElement?.outerHTML.length || 0,
      };
    });

    if (/verify|captcha|traffic/i.test(state.href) || /captcha|verify|xác minh|verification/i.test(state.text)) {
      return { ...job, error: 'Shopee yêu cầu verify/captcha, đã dừng. Không tự vượt captcha.' };
    }

    const scrolls = Math.max(1, Math.min(4, Number(options.scrolls || 2)));
    for (let i = 0; i < scrolls; i++) {
      await page.mouse.wheel(0, randomInt(420, 820));
      await humanDelay(options.scrollDelayMin, options.scrollDelayMax);
    }

    const products = await page.evaluate((limit) => {
      const rows = [];
      const seen = new Set();
      const anchors = Array.from(document.querySelectorAll('a[href*="-i."], a[href*="/product/"]'));
      for (const a of anchors) {
        const href = a.href || a.getAttribute('href') || '';
        if (!href || seen.has(href)) continue;
        seen.add(href);
        const card = a.closest('li, div[class*="col"], div') || a;
        const text = (card.innerText || a.innerText || '').replace(/\s+/g, ' ').trim();
        const title = a.getAttribute('title') || a.getAttribute('aria-label') || '';
        let name = title.trim();
        if (!name) {
          const parts = text.split(/ (?=₫|Đã bán|đã bán|Shop|Yêu thích|Mall)/).map((x) => x.trim()).filter(Boolean);
          name = parts.find((x) => x.length > 15 && !/^₫/.test(x)) || parts[0] || '';
        }
        const priceMatch = text.match(/₫\s*[0-9][0-9.,]*(?:\s*-\s*₫?\s*[0-9][0-9.,]*)?/);
        const soldMatch = text.match(/(?:Đã bán|đã bán|sold)\s*([0-9.,]+\s*(?:k|K|tr)?)/i);
        rows.push({ href, name, price_text: priceMatch ? priceMatch[0] : '', sold_text: soldMatch ? soldMatch[1] : '', raw_text: text.slice(0, 500) });
        if (rows.length >= limit) break;
      }
      return rows;
    }, options.limit);

    const normalized = [];
    for (const p of products) {
      const ids = parseProductId(p.href);
      if (!ids || !p.name) continue;
      const priceNumber = normalizePrice(p.price_text);
      const cleanName = p.name.replace(/^View product:\s*/i, '').trim();
      normalized.push({
        source_product_id: `SH-${ids.shopId}-${ids.itemId}`,
        product_name: cleanName,
        product_url: `https://shopee.vn/product/${ids.shopId}/${ids.itemId}`,
        price: priceNumber,
        sold_count: normalizeNumber(p.sold_text),
        notes: `Shopee CloakBrowser slow crawl${job.type === 'category' ? ` [${job.categoryName || job.categoryId}]` : ''}`,
        raw_url: p.href,
      });
    }

    return { ...job, products: normalized, count: normalized.length };
  } catch (error) {
    return { ...job, error: error?.message || String(error) };
  } finally {
    await page.close().catch(() => {});
  }
}

const payload = JSON.parse(process.argv[2] || '{}');
const jobs = Array.isArray(payload.jobs) ? payload.jobs : [];
const options = {
  limit: Math.max(1, Math.min(30, Number(payload.limit || process.env.SHOPEE_CLOAK_LIMIT || 12))),
  initialDelayMin: Math.max(3000, Number(process.env.SHOPEE_CLOAK_INITIAL_DELAY_MIN || 9000)),
  initialDelayMax: Math.max(5000, Number(process.env.SHOPEE_CLOAK_INITIAL_DELAY_MAX || 16000)),
  scrollDelayMin: Math.max(2000, Number(process.env.SHOPEE_CLOAK_SCROLL_DELAY_MIN || 5000)),
  scrollDelayMax: Math.max(3000, Number(process.env.SHOPEE_CLOAK_SCROLL_DELAY_MAX || 9000)),
  jobDelayMin: Math.max(5000, Number(process.env.SHOPEE_CLOAK_JOB_DELAY_MIN || 18000)),
  jobDelayMax: Math.max(7000, Number(process.env.SHOPEE_CLOAK_JOB_DELAY_MAX || 32000)),
  scrolls: Number(process.env.SHOPEE_CLOAK_SCROLLS || 2),
};

const userDataDir = payload.userDataDir || process.env.SHOPEE_CLOAK_PROFILE || 'storage/browser/shopee-cloak-profile';
fs.mkdirSync(path.resolve(userDataDir), { recursive: true });

const context = await launchPersistentContext({
  userDataDir,
  headless: payload.headless !== false,
  humanize: true,
  humanPreset: 'careful',
  locale: 'vi-VN',
  timezone: 'Asia/Ho_Chi_Minh',
  viewport: { width: 1366, height: 768 },
});

const results = [];
try {
  for (const job of jobs) {
    results.push(await scrapeJob(context, job, options));
    if (results.length < jobs.length) await humanDelay(options.jobDelayMin, options.jobDelayMax);
  }
  process.stdout.write(JSON.stringify({ ok: true, engine: 'cloakbrowser', slow: true, results }, null, 2));
} finally {
  await context.close().catch(() => {});
}
