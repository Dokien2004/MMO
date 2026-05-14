#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { launchPersistentContext } from 'cloakbrowser';

const keyword = process.argv.slice(2).join(' ').trim() || 'áo thun nam';
const userDataDir = process.env.SHOPEE_CLOAK_PROFILE || 'storage/browser/shopee-cloak-profile';
fs.mkdirSync(path.resolve(userDataDir), { recursive: true });

const context = await launchPersistentContext({
  userDataDir,
  headless: false,
  humanize: true,
  humanPreset: 'careful',
  locale: 'vi-VN',
  timezone: 'Asia/Ho_Chi_Minh',
  viewport: { width: 1366, height: 768 },
});

const page = context.pages()[0] || await context.newPage();
await page.goto(`https://shopee.vn/search?keyword=${encodeURIComponent(keyword)}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
console.log('Shopee CloakBrowser profile opened. Please solve login/verify manually if shown. Close browser when done.');

process.on('SIGINT', async () => {
  await context.close().catch(() => {});
  process.exit(0);
});
