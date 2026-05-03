#!/usr/bin/env node
'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const net = require('net');

const username = process.env.SHOPEE_LOGIN_USER || '';
const password = process.env.SHOPEE_LOGIN_PASS || '';
if (!username || !password) {
  console.error('Missing SHOPEE_LOGIN_USER/SHOPEE_LOGIN_PASS');
  process.exit(1);
}

const DEFAULT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

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
  const candidates = [process.env.CHROME_PATH, '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/snap/bin/chromium'].filter(Boolean);
  for (const candidate of candidates) if (fs.existsSync(candidate)) return candidate;
  throw new Error('Chrome/Chromium executable not found');
}

async function waitForDebugger(port, timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/json/list`);
      if (response.ok) {
        const targets = await response.json();
        const target = targets.find((item) => item.type === 'page' && item.webSocketDebuggerUrl);
        if (target) return target.webSocketDebuggerUrl;
      }
    } catch (_) {}
    await sleep(250);
  }
  throw new Error('Chrome remote debugger did not become ready');
}

class CdpClient {
  constructor(wsUrl) { this.wsUrl = wsUrl; this.socket = null; this.nextId = 1; this.pending = new Map(); }
  async connect() {
    await new Promise((resolve, reject) => {
      const socket = new WebSocket(this.wsUrl); this.socket = socket;
      socket.onopen = resolve;
      socket.onerror = (event) => reject(new Error(event.message || 'WebSocket error'));
      socket.onmessage = (event) => this.onMessage(event.data);
      socket.onclose = () => { for (const pending of this.pending.values()) pending.reject(new Error('CDP closed')); this.pending.clear(); };
    });
  }
  onMessage(raw) {
    const msg = JSON.parse(raw);
    if (!msg.id) return;
    const pending = this.pending.get(msg.id); if (!pending) return;
    this.pending.delete(msg.id);
    msg.error ? pending.reject(new Error(msg.error.message || 'CDP command failed')) : pending.resolve(msg.result);
  }
  send(method, params = {}) {
    const id = this.nextId++; const payload = JSON.stringify({ id, method, params });
    return new Promise((resolve, reject) => { this.pending.set(id, { resolve, reject }); this.socket.send(payload); });
  }
  close() { if (this.socket) this.socket.close(); }
}

async function evaluate(client, expression) {
  const result = await client.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) {
    const ex = result.exceptionDetails.exception || {};
    throw new Error(ex.description || ex.value || result.exceptionDetails.text || 'Evaluation failed');
  }
  return result.result ? result.result.value : null;
}

async function main() {
  const baseProfile = path.resolve(process.cwd(), 'storage/browser/shopee-profile');
  fs.mkdirSync(baseProfile, { recursive: true });
  const userDataDir = path.join(os.tmpdir(), `mmo-shopee-login-${process.pid}-${Date.now()}`);
  if (fs.existsSync(baseProfile)) {
    fs.cpSync(baseProfile, userDataDir, {
      recursive: true,
      force: true,
      errorOnExist: false,
      filter: (source) => !/Singleton(?:Cookie|Lock|Socket)$/.test(path.basename(source)),
    });
  } else {
    fs.mkdirSync(userDataDir, { recursive: true });
  }

  const port = await getFreePort();
  const chrome = spawn(findChromePath(), [
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${userDataDir}`,
    '--no-first-run', '--no-default-browser-check', '--no-sandbox', '--disable-dev-shm-usage',
    '--disable-blink-features=AutomationControlled', '--window-size=1365,900', '--lang=vi-VN',
    'https://shopee.vn/buyer/login',
  ], { stdio: 'ignore', windowsHide: true });

  let client;
  try {
    client = new CdpClient(await waitForDebugger(port));
    await client.connect();
    await client.send('Page.enable');
    await client.send('Runtime.enable');
    await client.send('Network.enable');
    await client.send('Network.setUserAgentOverride', { userAgent: DEFAULT_UA, acceptLanguage: 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7', platform: 'Windows' });
    await sleep(8000);

    const before = await evaluate(client, `({ href: location.href, title: document.title, text: document.body.innerText.slice(0, 1000) })`);
    const filled = await evaluate(client, `
      (async () => {
        const username = ${JSON.stringify(username)};
        const password = ${JSON.stringify(password)};
        const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
        const inputs = [...document.querySelectorAll('input')];
        const userInput = inputs.find((input) => /text|tel|email/.test(input.type || 'text')) || inputs[0];
        const passInput = inputs.find((input) => input.type === 'password') || inputs[1];
        if (!userInput || !passInput) return { ok: false, reason: 'login inputs not found', text: document.body.innerText.slice(0, 600), href: location.href };
        const setValue = (el, value) => {
          el.focus();
          const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
          setter.call(el, value);
          el.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertText', data: value }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
          el.blur();
        };
        setValue(userInput, username);
        await sleep(700);
        setValue(passInput, password);
        await sleep(1000);
        const buttons = [...document.querySelectorAll('button')];
        const loginButton = buttons.find((button) => /đăng nhập|log in|login/i.test(button.innerText || '') && !button.disabled)
          || buttons.find((button) => /đăng nhập|log in|login/i.test(button.innerText || ''))
          || buttons.find((button) => !button.disabled);
        if (!loginButton) return { ok: false, reason: 'login button not found', inputs: inputs.map((i) => ({ name: i.name, value: i.value, type: i.type })), text: document.body.innerText.slice(0, 600), href: location.href };
        loginButton.click();
        return { ok: true, href: location.href };
      })()
    `);

    await sleep(12000);
    const after = await evaluate(client, `({ href: location.href, title: document.title, text: document.body.innerText.slice(0, 1600), cookies: document.cookie.includes('SPC_EC') || document.cookie.includes('SPC_U') })`);

    const needsManual = /otp|mã xác minh|xác minh|captcha|không phải người máy|verification|verify|quét mã|qr/i.test(after.text || after.href || '');
    const loggedIn = !/buyer\/login/.test(after.href || '') && !/đăng nhập/i.test((after.text || '').slice(0, 500)) && !needsManual;

    console.log(JSON.stringify({ ok: true, before, filled, after, needsManual, loggedIn, profileTemp: userDataDir, profileDest: baseProfile }, null, 2));

    if (loggedIn || after.cookies) {
      fs.rmSync(baseProfile, { recursive: true, force: true });
      fs.mkdirSync(path.dirname(baseProfile), { recursive: true });
      fs.cpSync(userDataDir, baseProfile, {
        recursive: true,
        force: true,
        errorOnExist: false,
        filter: (source) => !/Singleton(?:Cookie|Lock|Socket)$/.test(path.basename(source)),
      });
    }
  } finally {
    if (client) client.close();
    if (!chrome.killed) chrome.kill();
  }
}

main().catch((error) => { console.error(error.message); process.exit(1); });
