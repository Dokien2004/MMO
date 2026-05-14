#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

function findChromePath() {
  const candidates = [
    process.env.CHROME_PATH,
    '/opt/google/chrome/chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
  ].filter(Boolean);
  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) return candidate;
  }
  throw new Error('Chrome executable not found');
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function waitForStableDom(page, timeoutMs = 45000) {
  const deadline = Date.now() + timeoutMs;
  let lastLen = 0;
  let stable = 0;
  while (Date.now() < deadline) {
    const state = await page.evaluate(() => ({
      href: location.href,
      title: document.title,
      readyState: document.readyState,
      len: document.documentElement.outerHTML.length,
      text: document.body ? document.body.innerText.slice(0, 800) : '',
      productish: document.querySelectorAll('[data-testid], [class*=price], [class*=Price], h1, img').length,
    }));

    if (/\/verify\/(traffic|captcha)/i.test(state.href)) {
      return state;
    }

    const ready = state.readyState === 'complete' || state.readyState === 'interactive';
    if (ready && state.productish > 10 && Math.abs(state.len - lastLen) < 3000) {
      stable += 1;
      if (stable >= 2) return state;
    } else {
      stable = 0;
    }
    lastLen = state.len;
    await sleep(2000);
  }
  return page.evaluate(() => ({ href: location.href, title: document.title, text: document.body ? document.body.innerText.slice(0, 800) : '' }));
}

function cleanText(value) {
  return String(value || '').replace(/\s+/g, ' ').trim();
}

async function extractProduct(page, inputLink) {
  return page.evaluate((inputLink) => {
    const clean = (v) => String(v || '').replace(/\s+/g, ' ').trim();
    const meta = (name) => document.querySelector(`meta[property="${name}"], meta[name="${name}"]`)?.content || '';
    const text = document.body ? document.body.innerText : '';

    const jsonLd = [...document.querySelectorAll('script[type="application/ld+json"]')]
      .map((s) => {
        try { return JSON.parse(s.textContent || '{}'); } catch (_) { return null; }
      })
      .filter(Boolean);
    const productLd = jsonLd.find((x) => x && (x['@type'] === 'Product' || (Array.isArray(x['@type']) && x['@type'].includes('Product')))) || {};

    const h1 = document.querySelector('h1')?.innerText || '';
    const title = clean(h1 || productLd.name || meta('og:title') || document.title);
    const description = clean(productLd.description || meta('og:description') || meta('description') || '');
    const image = productLd.image || meta('og:image') || document.querySelector('img[src*="shopee"]')?.src || '';

    const priceCandidates = [
      productLd.offers?.price,
      meta('product:price:amount'),
      ...[...document.querySelectorAll('[class*=price], [class*=Price]')].map((el) => el.innerText),
      text.match(/₫\s?[0-9][0-9\.,]*/)?.[0],
    ].filter(Boolean).map(clean);

    const soldMatch = text.match(/(?:đã bán|sold)\s*([0-9]+(?:[\.,][0-9]+)?\s*(?:k|nghìn|tr|m)?)/i);
    const ratingMatch = text.match(/([0-5](?:[\.,][0-9])?)\s*(?:\/\s*5|sao|star)/i);

    return {
      ok: true,
      source: 'shopee-dom',
      input_link: inputLink,
      final_url: location.href,
      title,
      description,
      price_text: priceCandidates[0] || '',
      price_candidates: [...new Set(priceCandidates)].slice(0, 8),
      image: Array.isArray(image) ? image[0] : image,
      sold_text: soldMatch ? soldMatch[0] : '',
      rating_text: ratingMatch ? ratingMatch[0] : '',
      page_title: document.title,
      text_preview: clean(text).slice(0, 1200),
    };
  }, inputLink);
}

async function main() {
  const input = JSON.parse(process.argv[2] || '{}');
  const link = String(input.link || '').trim();
  if (!/^https:\/\/([^/]+\.)?shopee\.vn\//i.test(link)) {
    throw new Error('Invalid Shopee link');
  }

  let browser;
  let launched = false;
  const cdpUrl = String(input.cdpUrl || process.env.SHOPEE_LIVE_CDP_URL || '').replace(/\/$/, '');
  if (cdpUrl) {
    try {
      browser = await puppeteer.connect({ browserURL: cdpUrl, defaultViewport: null });
    } catch (_) {
      browser = null;
    }
  }

  if (!browser) {
    const userDataDir = input.userDataDir || path.join(process.cwd(), 'storage/browser/shopee-product-profile');
    fs.mkdirSync(userDataDir, { recursive: true });
    browser = await puppeteer.launch({
      executablePath: findChromePath(),
      headless: false,
      userDataDir,
      defaultViewport: null,
      args: [
        '--no-sandbox',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-blink-features=AutomationControlled',
        '--window-size=1365,900',
        '--lang=vi-VN',
      ],
    });
    launched = true;
  }

  const page = await browser.newPage();
  await page.setExtraHTTPHeaders({
    'Accept-Language': 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
  });
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36');

  await page.goto(link, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await waitForStableDom(page, 60000);
  await sleep(3500);

  const currentUrl = page.url();
  if (/\/verify\/(traffic|captcha)/i.test(currentUrl)) {
    const body = cleanText(await page.evaluate(() => document.body ? document.body.innerText : ''));
    const result = {
      ok: false,
      needs_intervention: true,
      error: 'Shopee verification/captcha required',
      final_url: currentUrl,
      page_title: await page.title(),
      text_preview: body.slice(0, 1000),
    };
    process.stdout.write(JSON.stringify(result, null, 2));
    await page.close().catch(() => {});
    if (launched) await browser.close().catch(() => {}); else await browser.disconnect();
    return;
  }

  await page.evaluate(() => window.scrollBy({ top: Math.floor(window.innerHeight * 0.8), behavior: 'smooth' }));
  await sleep(2500);
  const result = await extractProduct(page, link);
  process.stdout.write(JSON.stringify(result, null, 2));

  await page.close().catch(() => {});
  if (launched) await browser.close().catch(() => {}); else await browser.disconnect();
}

main().catch((err) => {
  process.stderr.write(String(err && err.message ? err.message : err) + '\n');
  process.exit(1);
});
