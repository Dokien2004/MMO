#!/usr/bin/env node
'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const net = require('net');

const DEFAULT_UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function getFreePort() {
  return new Promise((resolve, reject) => {
    const server = net.createServer();
    server.listen(0, '127.0.0.1', () => {
      const address = server.address();
      server.close(() => resolve(address.port));
    });
    server.on('error', reject);
  });
}

function findChromePath() {
  const candidates = [
    process.env.CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  ].filter(Boolean);

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  throw new Error('Chrome/Edge executable not found');
}

async function waitForDebugger(port, timeoutMs) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/json/list`);
      if (response.ok) {
        const targets = await response.json();
        const pageTarget = targets.find((target) => target.type === 'page' && target.webSocketDebuggerUrl);
        if (pageTarget) {
          return pageTarget.webSocketDebuggerUrl;
        }
      }
    } catch (_error) {
    }
    await sleep(250);
  }

  throw new Error('Chrome remote debugger did not become ready in time');
}

class CdpClient {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.socket = null;
    this.nextId = 1;
    this.pending = new Map();
    this.events = new Map();
  }

  async connect() {
    await new Promise((resolve, reject) => {
      const socket = new WebSocket(this.wsUrl);
      this.socket = socket;

      socket.onopen = () => resolve();
      socket.onerror = (event) => reject(new Error(`WebSocket error: ${event.message || 'unknown error'}`));
      socket.onmessage = (event) => this.onMessage(event.data);
      socket.onclose = () => {
        for (const pending of this.pending.values()) {
          pending.reject(new Error('Chrome debugger connection closed'));
        }
        this.pending.clear();
      };
    });
  }

  on(method, handler) {
    if (!this.events.has(method)) {
      this.events.set(method, []);
    }
    this.events.get(method).push(handler);
  }

  onMessage(raw) {
    const message = JSON.parse(raw);
    if (message.id) {
      const pending = this.pending.get(message.id);
      if (!pending) {
        return;
      }
      this.pending.delete(message.id);
      if (message.error) {
        pending.reject(new Error(message.error.message || 'Chrome debugger command failed'));
        return;
      }
      pending.resolve(message.result);
      return;
    }

    const handlers = this.events.get(message.method) || [];
    for (const handler of handlers) {
      handler(message.params || {});
    }
  }

  async send(method, params = {}) {
    const id = this.nextId++;
    const payload = JSON.stringify({ id, method, params });

    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.socket.send(payload);
    });
  }

  async close() {
    if (!this.socket) {
      return;
    }
    this.socket.close();
    this.socket = null;
  }
}

function createLoadWaiter(client) {
  let resolver = null;
  let rejecter = null;
  let timeout = null;

  client.on('Page.loadEventFired', () => {
    if (!resolver) {
      return;
    }
    clearTimeout(timeout);
    const resolve = resolver;
    resolver = null;
    rejecter = null;
    timeout = null;
    resolve();
  });

  return (timeoutMs = 20000) =>
    new Promise((resolve, reject) => {
      resolver = resolve;
      rejecter = reject;
      timeout = setTimeout(() => {
        resolver = null;
        rejecter = null;
        timeout = null;
        reject(new Error('Timed out waiting for page load'));
      }, timeoutMs);
    });
}

async function navigate(client, waitForLoad, url) {
  const waiter = waitForLoad();
  await client.send('Page.navigate', { url });
  await waiter;
}

async function evaluate(client, expression) {
  const result = await client.send('Runtime.evaluate', {
    expression,
    awaitPromise: true,
    returnByValue: true,
  });

  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.text || 'Runtime evaluation failed');
  }

  return result.result ? result.result.value : null;
}

function buildJobConfig(job) {
  if (job.type === 'discover') {
    const page = Math.max(0, Number(job.page || 0));
    const offset = page * 60;
    return {
      pageUrl: 'https://shopee.vn/',
      apiUrl: `https://shopee.vn/api/v4/recommend/recommend?bundle=daily_discover_main&limit=60&offset=${offset}`,
      headers: {
        accept: 'application/json',
        'x-shopee-language': 'vi',
      },
    };
  }

  if (job.type === 'category') {
    const page = Math.max(1, Number(job.page || 1));
    const catId = Number(job.categoryId);
    const newest = (page - 1) * 60;
    return {
      pageUrl: `https://shopee.vn/search?category=${catId}&sortBy=sales`,
      apiUrl:
        'https://shopee.vn/api/v4/search/search_items?' +
        new URLSearchParams({
          by: 'sold',
          limit: '60',
          match_id: String(catId),
          newest: String(newest),
          order: 'desc',
          page_type: 'search',
          scenario: 'PAGE_CATEGORY',
          version: '2',
        }).toString(),
      headers: {
        accept: 'application/json',
        'x-shopee-language': 'vi',
        'x-api-source': 'pc',
      },
    };
  }

  if (job.type === 'search') {
    const page = Math.max(1, Number(job.page || 1));
    const newest = (page - 1) * 60;
    const sortMap = {
      sold: 'sold',
      price_asc: 'price',
      price_desc: 'price',
      relevance: 'relevance',
    };
    const by = sortMap[job.sortBy] || 'sold';
    const order = job.sortBy === 'price_desc' ? 'desc' : 'asc';
    const keyword = String(job.keyword || '');

    return {
      pageUrl: `https://shopee.vn/search?keyword=${encodeURIComponent(keyword)}`,
      apiUrl:
        'https://shopee.vn/api/v4/search/search_items?' +
        new URLSearchParams({
          keyword,
          limit: '60',
          newest: String(newest),
          order,
          page_type: 'search',
          scenario: 'PAGE_GLOBAL_SEARCH',
          by,
          version: '2',
        }).toString(),
      headers: {
        accept: 'application/json',
        'x-shopee-language': 'vi',
        'x-api-source': 'pc',
      },
    };
  }

  throw new Error(`Unsupported job type: ${job.type}`);
}

function normalizeShopeeItem(info, source) {
  const shopId = Number(info.shopid || 0);
  const itemId = Number(info.itemid || 0);
  if (!itemId) {
    return null;
  }

  return {
    source_product_id: `SH-${shopId}-${itemId}`,
    product_name: info.name || 'N/A',
    product_url: `https://shopee.vn/product/${shopId}/${itemId}`,
    price: Number(info.price || 0) / 100000,
    sold_count: Number(info.sold || info.historical_sold || 0),
    notes: source,
  };
}

function parseProducts(job, payload) {
  const products = [];

  if (job.type === 'discover') {
    const sections = (((payload || {}).data || {}).sections) || [];
    for (const section of sections) {
      const items = ((((section || {}).data || {}).item) || []);
      for (const item of items) {
        const parsed = normalizeShopeeItem(item, 'Shopee Daily Discover');
        if (parsed) {
          products.push(parsed);
        }
      }
    }
    return products;
  }

  const items = payload.items || payload.item_basic || [];
  const source =
    job.type === 'category'
      ? `Shopee [${job.categoryName || `Cat#${job.categoryId}`}]`
      : 'Scraped from Shopee search';

  for (const item of items) {
    const parsed = normalizeShopeeItem(item.item_basic || item, source);
    if (parsed) {
      products.push(parsed);
    }
  }

  return products;
}

function buildFetchExpression(config) {
  const serializedUrl = JSON.stringify(config.apiUrl);
  const serializedHeaders = JSON.stringify(config.headers);

  return `
    (async () => {
      const response = await fetch(${serializedUrl}, {
        method: 'GET',
        credentials: 'include',
        headers: ${serializedHeaders}
      });
      const text = await response.text();
      return { status: response.status, text };
    })()
  `;
}

async function runJob(client, waitForLoad, job) {
  const config = buildJobConfig(job);
  await navigate(client, waitForLoad, config.pageUrl);
  await sleep(2500);

  const fetchResult = await evaluate(client, buildFetchExpression(config));
  if (!fetchResult || typeof fetchResult.status !== 'number') {
    throw new Error('Browser fetch did not return a valid response');
  }
  if (fetchResult.status >= 400) {
    throw new Error(`HTTP ${fetchResult.status}`);
  }

  const payload = JSON.parse(fetchResult.text);
  return parseProducts(job, payload);
}

async function main() {
  const rawInput = process.argv[2];
  if (!rawInput) {
    throw new Error('Missing jobs payload');
  }

  const input = JSON.parse(rawInput);
  const jobs = Array.isArray(input.jobs) ? input.jobs : [];
  if (jobs.length === 0) {
    throw new Error('No jobs provided');
  }

  const chromePath = findChromePath();
  const port = await getFreePort();
  const userDataDir =
    input.userDataDir ||
    path.join(process.cwd(), 'storage', 'browser', 'shopee-profile');
  fs.mkdirSync(userDataDir, { recursive: true });

  const chromeArgs = [
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${userDataDir}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-blink-features=AutomationControlled',
    '--disable-background-networking',
    '--disable-features=Translate,OptimizationHints,MediaRouter',
    '--disable-popup-blocking',
    '--disable-renderer-backgrounding',
    '--window-size=1365,900',
    '--lang=vi-VN',
    '--headless=new',
    'about:blank',
  ];

  const chrome = spawn(chromePath, chromeArgs, {
    stdio: 'ignore',
    windowsHide: true,
  });

  let client = null;
  try {
    const wsUrl = await waitForDebugger(port, 30000);
    client = new CdpClient(wsUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('Runtime.enable');
    await client.send('Network.enable');
    await client.send('Page.addScriptToEvaluateOnNewDocument', {
      source: `
        Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
        Object.defineProperty(navigator, 'languages', { get: () => ['vi-VN', 'vi', 'en-US', 'en'] });
        Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
      `,
    });
    await client.send('Network.setUserAgentOverride', {
      userAgent: DEFAULT_UA,
      acceptLanguage: 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
      platform: 'Windows',
    });

    const waitForLoad = createLoadWaiter(client);
    const results = [];

    for (const job of jobs) {
      try {
        const products = await runJob(client, waitForLoad, job);
        results.push({ job, products });
      } catch (error) {
        results.push({ job, error: error.message });
      }
      await sleep(1200);
    }

    process.stdout.write(JSON.stringify({ ok: true, results }, null, 2));
  } finally {
    if (client) {
      await client.close().catch(() => {});
    }

    if (!chrome.killed) {
      chrome.kill();
    }
  }
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exit(1);
});
