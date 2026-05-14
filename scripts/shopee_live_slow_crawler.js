#!/usr/bin/env node
'use strict';

/**
 * Slow Shopee crawler that reuses an already logged-in Chromium window exposed
 * through Chrome DevTools Protocol.
 *
 * Defaults are intentionally conservative: one category page request every
 * 5 minutes, sorted by sales, and append-safe outputs for long runs.
 */

const fs = require('fs');
const path = require('path');

const CDP_URL = process.env.SHOPEE_LIVE_CDP_URL || 'http://127.0.0.1:19333';
const DELAY_MS = Number(process.env.SHOPEE_SLOW_DELAY_MS || 300000);
const MAX_PAGES_PER_CATEGORY = Number(process.env.SHOPEE_MAX_PAGES_PER_CATEGORY || 20);
const LIMIT = Number(process.env.SHOPEE_PAGE_LIMIT || 60);
const OUTPUT_JSON = process.env.SHOPEE_SLOW_OUTPUT || 'storage/data/sources/shopee_slow_sales_products.json';
const OUTPUT_JSONL = process.env.SHOPEE_SLOW_JSONL || 'storage/data/sources/shopee_slow_sales_products.jsonl';
const STATE_FILE = process.env.SHOPEE_SLOW_STATE || 'storage/data/sources/shopee_slow_sales_state.json';
const LOG_FILE = process.env.SHOPEE_SLOW_LOG || 'storage/logs/shopee_live_slow_crawler.log';

const CATEGORIES = [
  [11036132, 'Điện tử'],
  [11036030, 'Máy tính & Laptop'],
  [11036670, 'Nhà cửa & Đời sống'],
  [11035567, 'Thời Trang Nam'],
  [11035639, 'Thời Trang Nữ'],
  [11036279, 'Sức khỏe & Sắc đẹp'],
  [11036525, 'Bách hóa online'],
  [11036594, 'Phụ kiện & Trang sức'],
  [11036915, 'Đồ chơi'],
  [11036101, 'Thiết bị điện gia dụng'],
  [11035853, 'Giày dép nam'],
  [11035801, 'Giày dép nữ'],
  [11036382, 'Mẹ & Bé'],
  [11036812, 'Thể thao & Du lịch'],
];

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function ensureParent(file) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
}

function log(event, data = {}) {
  const line = JSON.stringify({ ts: new Date().toISOString(), event, ...data });
  ensureParent(LOG_FILE);
  fs.appendFileSync(LOG_FILE, line + '\n');
  console.log(line);
}

class CdpClient {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.socket = null;
    this.nextId = 1;
    this.pending = new Map();
  }

  async connect() {
    await new Promise((resolve, reject) => {
      this.socket = new WebSocket(this.wsUrl);
      this.socket.onopen = resolve;
      this.socket.onerror = (event) => reject(new Error(event.message || 'WebSocket error'));
      this.socket.onmessage = (event) => this.onMessage(event.data);
      this.socket.onclose = () => {
        for (const pending of this.pending.values()) pending.reject(new Error('CDP closed'));
        this.pending.clear();
      };
    });
  }

  onMessage(raw) {
    const message = JSON.parse(raw);
    if (!message.id) return;
    const pending = this.pending.get(message.id);
    if (!pending) return;
    this.pending.delete(message.id);
    message.error ? pending.reject(new Error(message.error.message || 'CDP command failed')) : pending.resolve(message.result);
  }

  send(method, params = {}) {
    const id = this.nextId++;
    this.socket.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
  }

  close() {
    if (this.socket) this.socket.close();
  }
}

async function getPageWsUrl() {
  const response = await fetch(`${CDP_URL}/json/list`);
  if (!response.ok) throw new Error(`Cannot list CDP tabs: HTTP ${response.status}`);
  const targets = await response.json();
  const target = targets.find((item) => item.type === 'page' && item.webSocketDebuggerUrl);
  if (!target) throw new Error('No CDP page target found');
  return target.webSocketDebuggerUrl;
}

async function evaluate(client, expression) {
  const result = await client.send('Runtime.evaluate', {
    expression,
    awaitPromise: true,
    returnByValue: true,
  });
  if (result.exceptionDetails) {
    const exception = result.exceptionDetails.exception || {};
    throw new Error(exception.description || exception.value || result.exceptionDetails.text || 'Runtime evaluation failed');
  }
  return result.result ? result.result.value : null;
}

function normalizeItem(info, source) {
  const shopId = Number(info.shopid || 0);
  const itemId = Number(info.itemid || 0);
  if (!itemId) return null;
  return {
    source_product_id: `SH-${shopId}-${itemId}`,
    product_name: info.name || 'N/A',
    product_url: `https://shopee.vn/product/${shopId}/${itemId}`,
    price: Number(info.price || 0) / 100000,
    sold_count: Number(info.sold || info.historical_sold || 0),
    notes: source,
    scraped_at: new Date().toISOString(),
  };
}

function parseProducts(payload, source) {
  const lists = [
    payload.items,
    payload.item_basic,
    payload.data && payload.data.items,
    payload.data && payload.data.item_basic,
  ].filter(Array.isArray);
  const products = [];
  for (const list of lists) {
    for (const item of list) {
      const product = normalizeItem(item.item_basic || item, source);
      if (product) products.push(product);
    }
  }
  return [...new Map(products.map((product) => [product.source_product_id, product])).values()];
}

function loadAggregate() {
  try {
    if (!fs.existsSync(OUTPUT_JSON)) return new Map();
    const rows = JSON.parse(fs.readFileSync(OUTPUT_JSON, 'utf8'));
    if (!Array.isArray(rows)) return new Map();
    return new Map(rows.filter((row) => row && row.source_product_id).map((row) => [row.source_product_id, row]));
  } catch (_error) {
    return new Map();
  }
}

function saveProducts(products) {
  if (products.length === 0) return { added: 0, total: loadAggregate().size };
  const aggregate = loadAggregate();
  let added = 0;
  ensureParent(OUTPUT_JSON);
  ensureParent(OUTPUT_JSONL);
  for (const product of products) {
    if (!aggregate.has(product.source_product_id)) added += 1;
    const previous = aggregate.get(product.source_product_id) || {};
    aggregate.set(product.source_product_id, { ...previous, ...product });
    fs.appendFileSync(OUTPUT_JSONL, JSON.stringify(product, null, 0) + '\n');
  }
  const sorted = [...aggregate.values()].sort((a, b) => Number(b.sold_count || 0) - Number(a.sold_count || 0));
  fs.writeFileSync(OUTPUT_JSON, JSON.stringify(sorted, null, 2));
  return { added, total: sorted.length };
}

function saveState(state) {
  ensureParent(STATE_FILE);
  fs.writeFileSync(STATE_FILE, JSON.stringify({ ...state, updated_at: new Date().toISOString() }, null, 2));
}

function buildCategoryUrl(categoryId, page) {
  return `https://shopee.vn/search?category=${categoryId}&page=${page}&sortBy=sales`;
}

function buildApiUrl(categoryId, page) {
  const newest = page * LIMIT;
  return 'https://shopee.vn/api/v4/search/search_items?' + new URLSearchParams({
    by: 'sales',
    limit: String(LIMIT),
    match_id: String(categoryId),
    newest: String(newest),
    order: 'desc',
    page_type: 'search',
    scenario: 'PAGE_CATEGORY',
    source: 'SRP',
    version: '2',
  }).toString();
}

async function crawlCategoryPage(client, categoryId, categoryName, page) {
  const pageUrl = buildCategoryUrl(categoryId, page);
  const apiUrl = buildApiUrl(categoryId, page);
  await client.send('Page.navigate', { url: pageUrl }).catch(() => {});
  await sleep(8000);
  const state = await evaluate(client, `({ href: location.href, text: document.body.innerText.slice(0, 500), hasLoginCookie: /SPC_EC|SPC_U|SPC_ST/.test(document.cookie) })`);
  if (/verify\/traffic|buyer\/login/.test(String(state.href || '')) || !state.hasLoginCookie) {
    throw new Error(`Shopee session not trusted/login missing. href=${state.href}; text=${String(state.text || '').replace(/\s+/g, ' ').slice(0, 220)}`);
  }
  const fetchResult = await evaluate(client, `
    (async () => {
      const response = await fetch(${JSON.stringify(apiUrl)}, {
        method: 'GET',
        credentials: 'include',
        headers: {
          accept: 'application/json',
          'x-shopee-language': 'vi',
          'x-api-source': 'pc'
        }
      });
      const text = await response.text();
      return { status: response.status, text };
    })()
  `);
  if (!fetchResult || fetchResult.status >= 400) {
    throw new Error(`Shopee API HTTP ${fetchResult ? fetchResult.status : 'unknown'}`);
  }
  const payload = JSON.parse(fetchResult.text || '{}');
  if (payload.error) throw new Error(`Shopee API marketplace error ${payload.error}`);
  return parseProducts(payload, `Shopee [${categoryName}] page ${page + 1} sorted by sales`);
}

async function main() {
  log('start', { cdp: CDP_URL, delay_ms: DELAY_MS, max_pages_per_category: MAX_PAGES_PER_CATEGORY, output: OUTPUT_JSON });
  const client = new CdpClient(await getPageWsUrl());
  await client.connect();
  await client.send('Page.enable');
  await client.send('Runtime.enable');

  try {
    for (const [categoryId, categoryName] of CATEGORIES) {
      for (let page = 0; page < MAX_PAGES_PER_CATEGORY; page += 1) {
        const state = { categoryId, categoryName, page, delay_ms: DELAY_MS };
        saveState({ ...state, status: 'running' });
        try {
          log('crawl_page_start', state);
          const products = await crawlCategoryPage(client, categoryId, categoryName, page);
          const saved = saveProducts(products);
          log('crawl_page_done', { ...state, count: products.length, added: saved.added, total: saved.total });
          saveState({ ...state, status: 'done', count: products.length, added: saved.added, total: saved.total });
          if (products.length === 0) {
            log('category_empty_stop', state);
            break;
          }
        } catch (error) {
          log('crawl_page_error', { ...state, error: error.message });
          saveState({ ...state, status: 'error', error: error.message });
          if (/session not trusted|login|verify|90309999/i.test(error.message)) {
            throw error;
          }
        }
        log('sleep', { ms: DELAY_MS });
        await sleep(DELAY_MS);
      }
    }
    saveState({ status: 'finished' });
    log('finished');
  } finally {
    client.close();
  }
}

main().catch((error) => {
  log('fatal', { error: error.stack || error.message });
  process.exit(1);
});
