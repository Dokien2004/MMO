#!/usr/bin/env node
'use strict';

const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const net = require('net');

const BASE_DIR = path.resolve(__dirname, '..');
const profileDest = path.join(BASE_DIR, 'storage/browser/shopee-profile');
const screenshotPath = process.env.SHOPEE_QR_SCREENSHOT || path.join(BASE_DIR, 'storage/browser/shopee-login-qr.png');
const timeoutMs = Number(process.env.SHOPEE_QR_TIMEOUT_MS || 300000);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function getFreePort() { return new Promise((resolve, reject) => { const s = net.createServer(); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => resolve(p)); }); s.on('error', reject); }); }
function findChromePath() { for (const p of [process.env.CHROME_PATH, '/usr/bin/chromium-browser', '/usr/bin/chromium', '/snap/bin/chromium', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable'].filter(Boolean)) if (fs.existsSync(p)) return p; throw new Error('Chrome/Chromium executable not found'); }
async function waitForDebugger(port, timeout = 30000) { const end = Date.now() + timeout; while (Date.now() < end) { try { const r = await fetch(`http://127.0.0.1:${port}/json/list`); if (r.ok) { const j = await r.json(); const t = j.find((x) => x.type === 'page' && x.webSocketDebuggerUrl); if (t) return t.webSocketDebuggerUrl; } } catch (_) {} await sleep(250); } throw new Error('Chrome remote debugger did not become ready'); }
class CdpClient { constructor(u) { this.u = u; this.id = 1; this.pending = new Map(); } async connect() { await new Promise((resolve, reject) => { this.socket = new WebSocket(this.u); this.socket.onopen = resolve; this.socket.onerror = (e) => reject(new Error(e.message || 'WebSocket error')); this.socket.onmessage = (e) => this.onMessage(e.data); this.socket.onclose = () => { for (const p of this.pending.values()) p.reject(new Error('CDP closed')); this.pending.clear(); }; }); } onMessage(raw) { const m = JSON.parse(raw); if (!m.id) return; const p = this.pending.get(m.id); if (!p) return; this.pending.delete(m.id); m.error ? p.reject(new Error(m.error.message || 'CDP command failed')) : p.resolve(m.result); } send(method, params = {}) { const id = this.id++; this.socket.send(JSON.stringify({ id, method, params })); return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject })); } close() { this.socket?.close(); } }
async function evaluate(c, expression) { const r = await c.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }); if (r.exceptionDetails) { const e = r.exceptionDetails.exception || {}; throw new Error(e.description || e.value || r.exceptionDetails.text || 'Evaluation failed'); } return r.result?.value; }
async function copyProfile(src, dst) { fs.rmSync(dst, { recursive: true, force: true }); fs.mkdirSync(path.dirname(dst), { recursive: true }); fs.cpSync(src, dst, { recursive: true, force: true, errorOnExist: false, filter: (source) => !/Singleton(?:Cookie|Lock|Socket)$/.test(path.basename(source)) }); }
async function screenshot(c) { const shot = await c.send('Page.captureScreenshot', { format: 'png' }); fs.writeFileSync(screenshotPath, Buffer.from(shot.data, 'base64')); }

async function main() {
  fs.mkdirSync(path.dirname(screenshotPath), { recursive: true });
  const userDataDir = path.join(os.tmpdir(), `mmo-shopee-qr-${process.pid}-${Date.now()}`);
  if (fs.existsSync(profileDest)) fs.cpSync(profileDest, userDataDir, { recursive: true, force: true, errorOnExist: false, filter: (source) => !/Singleton(?:Cookie|Lock|Socket)$/.test(path.basename(source)) }); else fs.mkdirSync(userDataDir, { recursive: true });
  const port = await getFreePort();
  const chrome = spawn(findChromePath(), [`--remote-debugging-port=${port}`, `--user-data-dir=${userDataDir}`, '--no-first-run', '--no-default-browser-check', '--no-sandbox', '--disable-dev-shm-usage', '--window-size=1200,950', '--lang=vi-VN', 'https://shopee.vn/buyer/login'], { stdio: 'ignore', windowsHide: true });
  let client;
  try {
    client = new CdpClient(await waitForDebugger(port)); await client.connect();
    await client.send('Page.enable'); await client.send('Runtime.enable'); await client.send('Network.enable');
    await sleep(12000);
    const qrRect = await evaluate(client, `(() => { const els = [...document.querySelectorAll('button,a,div,span')].filter(el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length)); const area = el => { const r = el.getBoundingClientRect(); return r.width*r.height; }; const el = els.filter(el => /log in with qr|đăng nhập.*qr/i.test((el.innerText || '').trim())).sort((a,b)=>area(a)-area(b))[0]; if (!el) return null; const r = el.getBoundingClientRect(); return { x: r.left + r.width/2, y: r.top + r.height/2, text: el.innerText, rect: { left:r.left, top:r.top, width:r.width, height:r.height } }; })()`);
    if (qrRect) {
      await evaluate(client, `(() => {
        const points = [[1138, 166], [1146, 148], [1128, 178]];
        for (const [x, y] of points) {
          const el = document.elementFromPoint(x, y);
          if (!el) continue;
          const target = el.closest('button,a,div,span') || el;
          target.click();
        }
        return true;
      })()`);
      await sleep(1800);
      const clickPoints = [
        { x: qrRect.rect.left + qrRect.rect.width + 28, y: qrRect.y },
        { x: qrRect.rect.left + qrRect.rect.width + 54, y: qrRect.y },
        { x: qrRect.x, y: qrRect.y },
      ];
      for (const point of clickPoints) {
        await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: point.x, y: point.y });
        await client.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: point.x, y: point.y, button: 'left', clickCount: 1 });
        await client.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: point.x, y: point.y, button: 'left', clickCount: 1 });
        await sleep(1200);
      }
    }
    await sleep(7000);
    await screenshot(client);
    const pageState = await evaluate(client, `({ href: location.href, title: document.title, text: document.body.innerText.slice(0, 1000), qrImages: [...document.querySelectorAll('canvas,img,svg')].map((el) => { const r = el.getBoundingClientRect(); return { tag: el.tagName, x:r.x, y:r.y, w:r.width, h:r.height, src: el.src || '' }; }).filter(x => x.w > 80 && x.h > 80).slice(0,10) })`);
    console.log(JSON.stringify({ event: 'QR_READY', screenshotPath, timeoutMs, clicked: qrRect, pageState }, null, 2));
    const deadline = Date.now() + timeoutMs;
    while (Date.now() < deadline) {
      await sleep(5000);
      const state = await evaluate(client, `({ href: location.href, text: document.body.innerText.slice(0, 800), cookie: document.cookie })`);
      const hasLoginCookie = /SPC_EC|SPC_U|SPC_ST/.test(state.cookie || '');
      const awayFromLogin = !/buyer\/login|verify\/traffic\/error/.test(state.href || '');
      const needsVerify = /security check|xác minh|verify|otp|captcha|không khả dụng|not available/i.test((state.text || '') + ' ' + (state.href || ''));
      if ((hasLoginCookie || awayFromLogin) && !needsVerify) { await copyProfile(userDataDir, profileDest); console.log(JSON.stringify({ event: 'LOGIN_SUCCESS', profileDest, href: state.href }, null, 2)); return; }
      if (needsVerify && !/buyer\/login/.test(state.href || '')) console.log(JSON.stringify({ event: 'NEEDS_MANUAL_VERIFY', href: state.href, text: state.text }, null, 2));
    }
    console.log(JSON.stringify({ event: 'LOGIN_TIMEOUT', screenshotPath }, null, 2));
  } finally { client?.close(); if (!chrome.killed) chrome.kill(); }
}
main().catch((e) => { console.error(e.message); process.exit(1); });
