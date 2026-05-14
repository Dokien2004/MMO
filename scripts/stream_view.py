#!/usr/bin/env python3
"""Stream live Shopee browser viewport as MJPEG over HTTP."""
import subprocess, time, json, sys, os, base64, signal
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import threading

CDP = 'http://127.0.0.1:19333'
HOST = '0.0.0.0'
PORT = 18100
TOKEN_FILE = '/home/dokien/.openclaw/workspace/MMO/storage/logs/cdp_remote_control_access.txt'
TOKEN = '0vH-RzUvFjKL-ssEDZ3bMmIS'  # fallback

try:
    with open(TOKEN_FILE) as f:
        for line in f:
            if line.startswith('TOKEN='):
                TOKEN = line.strip().split('=', 1)[1]
                break
except:
    pass

class StreamHandler(BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'
    frame = b''
    last_img = b''
    last_meta = {'url': '', 'title': '', 'cssW': 1, 'cssH': 1}

    def do_GET(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)
        if qs.get('token', [''])[0] != TOKEN:
            self.send_error(403, 'Forbidden')
            return
        if parsed.path == '/':
            self.send_response(200)
            self.send_header('content-type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(self.html().encode())
        elif parsed.path == '/stream.mjpg':
            self.send_response(200)
            self.send_header('content-type', 'multipart/x-mixed-replace; boundary=mjpeg')
            self.send_header('cache-control', 'no-cache')
            self.end_headers()
            self.stream_frame()
        elif parsed.path == '/state':
            self.send_response(200)
            self.send_header('content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(self.last_meta).encode())
        elif parsed.path == '/click':
            self.send_response(200)
            self.send_header('content-type', 'application/json')
            self.end_headers()
            self.wfile.write(b'{"ok":false,"note":"use POST"}')
        elif parsed.path == '/click':
            self.send_response(200)
            self.send_header('content-type', 'application/json')
            self.end_headers()
            args = {k: v[0] for k, v in qs.items()}
            x, y = float(args.get('x', 0)), float(args.get('y', 0))
            self.wfile.write(json.dumps({'ok': True, 'x': x, 'y': y}).encode())
        else:
            self.send_error(404)

    def do_POST(self):
        parsed = urlparse(self.path)
        qs = parse_qs(parsed.query)
        if qs.get('token', [''])[0] != TOKEN:
            self.send_error(403, 'Forbidden')
            return
        if parsed.path == '/click' or parsed.path == '/press':
            cl = int(self.headers.get('content-length', 0))
            data = self.rfile.read(cl) if cl > 0 else b'{}'
            try:
                body = json.loads(data)
            except:
                body = {}
            if parsed.path == '/click':
                x = float(body.get('x', 0))
                y = float(body.get('y', 0))
                self._click(x, y)
            elif parsed.path == '/press':
                self._press(str(body.get('key', 'Enter')))
            elif parsed.path == '/type':
                self._type(str(body.get('text', '')))
            self.send_response(200)
            self.send_header('content-type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({'ok': True}).encode())

    def _click(self, x, y):
        self._cdp('Input.dispatchMouseEvent', {'type': 'mousePressed', 'x': x, 'y': y, 'button': 'left', 'clickCount': 1})
        self._cdp('Input.dispatchMouseEvent', {'type': 'mouseReleased', 'x': x, 'y': y, 'button': 'left', 'clickCount': 1})

    def _press(self, key):
        self._cdp('Input.dispatchKeyEvent', {'type': 'keyDown', 'key': key})
        self._cdp('Input.dispatchKeyEvent', {'type': 'keyUp', 'key': key})

    def _type(self, text):
        for ch in text:
            self._cdp('Input.insertText', {'text': ch})

    def _cdp(self, method, params=None):
        import urllib.request
        req = urllib.request.Request(
            f'{CDP}/json/current',
            headers={'Accept': 'application/json'}
        )
        with urllib.request.urlopen(req, timeout=5) as r:
            target = json.loads(r.read())
        ws_url = target.get('webSocketDebuggerUrl', '')
        if not ws_url:
            return
        import websockets, asyncio
        async def send():
            async with websockets.connect(ws_url) as ws:
                await ws.send(json.dumps({'id': 1, 'method': method, 'params': params or {}}))
                await ws.recv()
        try:
            asyncio.run(send())
        except:
            pass

    def stream_frame(self):
        for _ in range(300):
            try:
                img, meta = self._capture()
                if img:
                    header = b'--mjpeg\r\ncontent-type: image/jpeg\r\ncontent-length: %d\r\n\r\n' % len(img)
                    self.wfile.write(header + img + b'\r\n')
                    self.last_meta = meta
                    self.last_img = img
                time.sleep(0.5)
            except Exception as e:
                break

    def _capture(self):
        import urllib.request
        try:
            # Get page info
            req = urllib.request.Request(f'{CDP}/json/current', headers={'Accept': 'application/json'})
            with urllib.request.urlopen(req, timeout=5) as r:
                target = json.loads(r.read())
            ws_url = target.get('webSocketDebuggerUrl', '')
            if not ws_url:
                return None, {}

            import websockets, asyncio

            async def capture():
                async with websockets.connect(ws_url) as ws:
                    # Page info
                    await ws.send(json.dumps({'id': 1, 'method': 'Runtime.evaluate', 'params': {'expression': '({url: location.href, title: document.title, cssW: document.documentElement.clientWidth, cssH: document.documentElement.clientHeight})', 'returnByValue': True}}))
                    info = json.loads(await ws.recv())
                    meta = info.get('result', {}).get('result', {}).get('value', {})
                    # Screenshot
                    await ws.send(json.dumps({'id': 2, 'method': 'Page.captureScreenshot', 'params': {'format': 'jpeg', 'quality': 80, 'fromSurface': True}}))
                    shot = json.loads(await ws.recv())
                    img_b64 = shot.get('result', {}).get('data', '')
                    img = base64.b64decode(img_b64) if img_b64 else b''
                    return img, {'url': meta.get('url', ''), 'title': meta.get('title', ''), 'cssW': meta.get('cssW', 1365), 'cssH': meta.get('cssH', 900)}
            return asyncio.run(capture())
        except Exception as e:
            return None, {}

    def html(self):
        return f'''<!doctype html>
<html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Shopee Live View</title>
<style>
*{{margin:0;padding:0;box-sizing:border-box}}
body{{background:#0a0a0a;color:#eee;font-family:system-ui,sans-serif}}
header{{background:#111;padding:12px 16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;position:sticky;top:0;z-index:9}}
button{{background:#1e3a5f;color:#fff;border:1px solid #334155;padding:7px 14px;border-radius:7px;cursor:pointer;font-size:13px}}
button:hover{{background:#2d5a8a}}
input{{background:#1e293b;color:#e2e8f0;border:1px solid #334155;padding:7px 10px;border-radius:7px;width:220px;font-size:13px}}
img{{max-width:100%;display:block;border:1px solid #1e293b;background:#000;cursor:crosshair}}
.info{{padding:8px 16px;background:#1a1a2e;color:#94a3b8;font-size:12px}}
</style></head><body>
<header>
  <strong style="color:#60a5fa">Shopee Live</strong>
  <span id="info" style="flex:1;color:#94a3b8;font-size:13px">Đang kết nối...</span>
  <input id="txt" placeholder="Gõ text rồi Enter">
  <button onclick="sendTxt()">Send</button>
  <button onclick="sendKey('Enter')">Enter</button>
  <button onclick="location.reload()">Refresh</button>
</header>
<div style="padding:12px">
  <img id="screenshot" onclick="doClick(event)" alt="loading">
</div>
<script>
const tok = "{TOKEN}";
const img = document.getElementById('screenshot');
const info = document.getElementById('info');
let cssW=1365, cssH=900;

async function api(path, body){{
  const res = await fetch(path + '?token=' + tok, {{method: body?'POST':'GET', headers:{{'content-type':'application/json'}}, body: body?JSON.stringify(body):undefined}});
  return res.json();
}}
async function refresh(){{
  try{{
    const s = await api('/state');
    if(s.url) info.textContent = s.title || s.url;
    if(s.cssW){{cssW=s.cssW;cssH=s.cssH;}}
    img.src = '/stream.mjpg?token=' + tok + '&t=' + Date.now();
  }}catch(e){{console.error(e);}}
  setTimeout(refresh, 3000);
}}
function doClick(e){{
  const r = img.getBoundingClientRect();
  const x = (e.clientX - r.left) * cssW / r.width;
  const y = (e.clientY - r.top) * cssH / r.height;
  api('/click', {{x,y}}).then(()=>{{setTimeout(refresh, 500);}}).catch(console.error);
}}
async function sendTxt(){{
  const t = document.getElementById('txt').value;
  if(!t) return;
  for(const c of t){{await api('/type', {{text:c}}); await new Promise(r=>setTimeout(r,80));}}
  document.getElementById('txt').value='';
}}
async function sendKey(k){{await api('/press', {{key:k}}); setTimeout(refresh,400);}}
document.getElementById('txt').addEventListener('keydown', e=>{{if(e.key==='Enter'){{e.preventDefault();sendTxt();}}}});
refresh();
</script></body></html>'''

    def log_message(self, fmt, *args):
        pass

if __name__ == '__main__':
    server = HTTPServer((HOST, PORT), StreamHandler)
    print(f'Live stream: http://0.0.0.0:{PORT}/?token={TOKEN}')
    server.serve_forever()