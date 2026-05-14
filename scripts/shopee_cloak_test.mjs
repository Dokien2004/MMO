#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { launchContext } from 'cloakbrowser';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const keyword = process.argv.slice(2).join(' ').trim() || 'áo thun nam';
const limit = Math.max(1, Math.min(10, Number(process.env.LIMIT || 5)));
const outFile = path.resolve('storage/data/sources/shopee_cloak_test.json');

function normalizeProduct(item) {
  const href = String(item.href || '');
  let url = href;
  if (url && url.startsWith('/')) url = 'https://shopee.vn' + url;
  return {
    name: String(item.name || '').replace(/\s+/g, ' ').trim(),
    url,
    price: String(item.price || '').replace(/\s+/g, ' ').trim(),
    sold: String(item.sold || '').replace(/\s+/g, ' ').trim(),
  };
}

const context = await launchContext({
  headless: true,
  humanize: true,
  humanPreset: 'careful',
  locale: 'vi-VN',
  timezone: 'Asia/Ho_Chi_Minh',
  viewport: { width: 1366, height: 768 },
});

try {
  const page = await context.newPage();
  const url = `https://shopee.vn/search?keyword=${encodeURIComponent(keyword)}`;
  console.error(`[cloak-test] open ${url}`);
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await sleep(9000);

  const state = await page.evaluate(() => {
    const text = document.body?.innerText || '';
    return {
      href: location.href,
      title: document.title,
      text: text.slice(0, 1000),
      productLinks: document.querySelectorAll('a[href*="-i."], a[href*="/product/"]').length,
      htmlLength: document.documentElement?.outerHTML.length || 0,
    };
  });

  if (/verify|captcha|traffic/i.test(state.href) || /captcha|verify|xác minh|verification/i.test(state.text)) {
    const result = { ok: false, reason: 'Shopee yêu cầu verify/captcha, đã dừng theo quy tắc an toàn.', keyword, state };
    fs.mkdirSync(path.dirname(outFile), { recursive: true });
    fs.writeFileSync(outFile, JSON.stringify(result, null, 2));
    console.log(JSON.stringify(result, null, 2));
    process.exit(2);
  }

  await page.mouse.wheel(0, 520);
  await sleep(3500);
  await page.mouse.wheel(0, 680);
  await sleep(5000);

  const products = await page.evaluate((limit) => {
    const anchors = Array.from(document.querySelectorAll('a[href*="-i."], a[href*="/product/"]'));
    const rows = [];
    const seen = new Set();
    for (const a of anchors) {
      const href = a.getAttribute('href') || '';
      if (!href || seen.has(href)) continue;
      seen.add(href);
      const card = a.closest('li, div') || a;
      const text = (card.innerText || a.innerText || '').split('\n').map((x) => x.trim()).filter(Boolean);
      const name = a.getAttribute('title') || text.find((x) => x.length > 12 && !/^₫|^đ|đã bán/i.test(x)) || text[0] || '';
      const price = text.find((x) => /₫|đ/.test(x)) || '';
      const sold = text.find((x) => /đã bán|sold/i.test(x)) || '';
      rows.push({ name, href, price, sold });
      if (rows.length >= limit) break;
    }
    return rows;
  }, limit);

  const result = {
    ok: products.length > 0,
    keyword,
    count: products.length,
    state,
    products: products.map(normalizeProduct),
    tested_at: new Date().toISOString(),
  };
  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, JSON.stringify(result, null, 2));
  console.log(JSON.stringify(result, null, 2));
} finally {
  await context.close();
}
