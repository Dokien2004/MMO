#!/usr/bin/env node
'use strict';

const http = require('http');
const crypto = require('crypto');

const CDP_URL = (process.env.CDP_URL || 'http://127.0.0.1:19333').replace(/\/$/, '');
const HOST = process.env.REMOTE_CONTROL_HOST || '127.0.0.1';
const PORT = Number(process.env.REMOTE_CONTROL_PORT || 18090);
const TOKEN = process.env.REMOTE_CONTROL_TOKEN || crypto.randomBytes(18).toString('hex');
const WebSocketClient = globalThis.WebSocket || require('ws');

class CdpClient {
  constructor(wsUrl) {
    this.wsUrl = wsUrl;
    this.nextId = 1;
    this.pending = new Map();
  }

  async connect() {
    await new Promise((resolve, reject) => {
      this.socket = new WebSocketClient(this.wsUrl);
      this.socket.onopen = resolve;
      this.socket.onerror = (event) => reject(new Error(event.message || 'WebSocket error'));
      this.socket.onmessage = (event) => this.onMessage(event.data);
      this.socket.onclose = () => {
        for (const pending of this.pending.values()) pending.reject(new Error('CDP connection closed'));
        this.pending.clear();
      };
    });
    await this.send('Page.enable').catch(() => {});
    await this.send('Runtime.enable').catch(() => {});
  }

  onMessage(raw) {
    const msg = JSON.parse(raw);
    if (!msg.id) return;
    const pending = this.pending.get(msg.id);
    if (!pending) return;
    this.pending.delete(msg.id);
    if (msg.error) pending.reject(new Error(msg.error.message || 'CDP command failed'));
    else pending.resolve(msg.result);
  }

  send(method, params = {}) {
    const id = this.nextId++;
    this.socket.send(JSON.stringify({ id, method, params }));
    return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
  }
}

let clientPromise = null;
async function getClient() {
  if (clientPromise) return clientPromise;
  clientPromise = (async () => {
    const targets = await (await fetch(`${CDP_URL}/json/list`)).json();
    const target = targets.find((t) => t.type === 'page' && t.webSocketDebuggerUrl);
    if (!target) throw new Error('No CDP page target found');
    const c = new CdpClient(target.webSocketDebuggerUrl);
    await c.connect();
    return c;
  })().catch((err) => {
    clientPromise = null;
    throw err;
  });
  return clientPromise;
}

function send(res, status, body, type = 'application/json; charset=utf-8') {
  res.writeHead(status, {
    'content-type': type,
    'cache-control': 'no-store',
    'x-content-type-options': 'nosniff',
  });
  res.end(body);
}

function html() {
  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Shopee Live Control</title>
<style>
body{margin:0;background:#0f172a;color:#e2e8f0;font-family:system-ui,-apple-system,Segoe UI,sans-serif}header{padding:10px 14px;background:#111827;position:sticky;top:0;z-index:2;display:flex;gap:8px;align-items:center;flex-wrap:wrap}button,input{border:1px solid #334155;background:#1e293b;color:#e2e8f0;border-radius:8px;padding:8px 10px}button{cursor:pointer}.wrap{padding:10px}.screen{max-width:100%;border:1px solid #334155;border-radius:10px;background:#020617;touch-action:none}small{color:#94a3b8}.grow{flex:1;min-width:200px}.danger{color:#fecaca}</style>
</head>
<body>
<header>
  <strong>Shopee live browser</strong>
  <small id="meta" class="grow">Đang kết nối...</small>
  <input id="text" placeholder="Gõ text rồi Enter/Send" class="grow">
  <button onclick="sendText()">Send</button>
  <button onclick="press('Enter')">Enter</button>
  <button onclick="press('Escape')">Esc</button>
  <button onclick="refresh()">Refresh</button>
</header>
<div class="wrap">
  <p><small>Click trực tiếp vào ảnh để điều khiển. Nếu Shopee hiện captcha/verify, xử lý ngay trên màn hình này.</small></p>
  <img id="screen" class="screen" alt="live screenshot">
  <p id="err" class="danger"></p>
</div>
<script>
const token = new URLSearchParams(location.search).get('token') || '';
const img = document.getElementById('screen');
const meta = document.getElementById('meta');
const err = document.getElementById('err');
let cssW = 1, cssH = 1, dpr = 1;
async function api(path, body){
  const res = await fetch(path + '?token=' + encodeURIComponent(token), {method: body?'POST':'GET', headers:{'content-type':'application/json'}, body: body?JSON.stringify(body):undefined});
  const data = await res.json().catch(()=>({}));
  if(!res.ok) throw new Error(data.error || res.statusText);
  return data;
}
async function refresh(){
  try{
    const s = await api('/state');
    img.src = 'data:image/png;base64,' + s.image;
    cssW = s.cssWidth || 1; cssH = s.cssHeight || 1; dpr = s.dpr || 1;
    meta.textContent = (s.title || '') + ' — ' + (s.url || '');
    err.textContent = '';
  }catch(e){ err.textContent = e.message; }
}
img.addEventListener('click', async (ev)=>{
  const r = img.getBoundingClientRect();
  const x = (ev.clientX-r.left) * cssW / r.width;
  const y = (ev.clientY-r.top) * cssH / r.height;
  try{ await api('/click', {x,y}); setTimeout(refresh, 350); }catch(e){ err.textContent=e.message; }
});
document.getElementById('text').addEventListener('keydown', (e)=>{ if(e.key==='Enter') sendText(); });
async function sendText(){ const el=document.getElementById('text'); const text=el.value; if(!text) return; el.value=''; try{ await api('/type',{text}); setTimeout(refresh,350);}catch(e){err.textContent=e.message;} }
async function press(key){ try{ await api('/press',{key}); setTimeout(refresh,350);}catch(e){err.textContent=e.message;} }
refresh(); setInterval(refresh, 2500);
</script>
</body>
</html>`;
}

function requireToken(req, res, url) {
  if (url.searchParams.get('token') !== TOKEN) {
    send(res, 403, JSON.stringify({ error: 'Forbidden' }));
    return false;
  }
  return true;
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  try {
    if (url.pathname === '/') return send(res, 200, html(), 'text/html; charset=utf-8');
    if (!requireToken(req, res, url)) return;
    const c = await getClient();
    if (url.pathname === '/state') {
      const metrics = await c.send('Page.getLayoutMetrics');
      const css = metrics.cssVisualViewport || metrics.visualViewport || { clientWidth: 1365, clientHeight: 900, scale: 1 };
      const info = await c.send('Runtime.evaluate', { expression: '({url: location.href, title: document.title, dpr: devicePixelRatio})', returnByValue: true });
      const shot = await c.send('Page.captureScreenshot', { format: 'png', fromSurface: true });
      return send(res, 200, JSON.stringify({
        image: shot.data,
        cssWidth: css.clientWidth,
        cssHeight: css.clientHeight,
        url: info.result.value.url,
        title: info.result.value.title,
        dpr: info.result.value.dpr,
      }));
    }
    let body = '';
    req.on('data', (chunk) => body += chunk);
    req.on('end', async () => {
      try {
        const data = body ? JSON.parse(body) : {};
        if (url.pathname === '/click') {
          const x = Number(data.x || 0), y = Number(data.y || 0);
          await c.send('Input.dispatchMouseEvent', { type: 'mousePressed', x, y, button: 'left', clickCount: 1 });
          await c.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x, y, button: 'left', clickCount: 1 });
          return send(res, 200, JSON.stringify({ ok: true }));
        }
        if (url.pathname === '/type') {
          await c.send('Input.insertText', { text: String(data.text || '') });
          return send(res, 200, JSON.stringify({ ok: true }));
        }
        if (url.pathname === '/press') {
          const key = String(data.key || 'Enter');
          await c.send('Input.dispatchKeyEvent', { type: 'keyDown', key });
          await c.send('Input.dispatchKeyEvent', { type: 'keyUp', key });
          return send(res, 200, JSON.stringify({ ok: true }));
        }
        return send(res, 404, JSON.stringify({ error: 'Not found' }));
      } catch (e) {
        return send(res, 500, JSON.stringify({ error: e.message }));
      }
    });
  } catch (e) {
    clientPromise = null;
    send(res, 500, JSON.stringify({ error: e.message }));
  }
});

server.listen(PORT, HOST, () => {
  console.log(`CDP remote control listening: http://${HOST}:${PORT}/?token=${TOKEN}`);
  console.log(`CDP target: ${CDP_URL}`);
});
