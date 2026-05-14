#!/usr/bin/env python3
"""VNC server using libvncserver + CDP screen capture."""
import socketserver, threading, time, struct, json, subprocess, os
from ctypes import cdll, CFUNCTYPE, c_int, c_char_p, pointer, cast
from pathlib import Path

# Load libvncserver
libvnc = CDLL('/usr/lib/x86_64-linux-gnu/libvncserver.so.1')

# Constants
RFBCLIENT = 0
WN_Desktop = 0

class VNCServer:
    def __init__(self, port=5900):
        self.port = port
        self.sock = None
        self.running = False
        self.clients = []
        self.screen_buf = None
        self.screen_w = 1365
        self.screen_h = 900

    def capture_screen(self):
        """Capture screen using ffmpeg + ImageMagick"""
        try:
            # Use import (ImageMagick) to capture
            result = subprocess.run([
                'import', '-window', 'root', '-silent', '/tmp/screen_cap.png'
            ], capture_output=True, timeout=5)
            if os.path.exists('/tmp/screen_cap.png'):
                with open('/tmp/screen_cap.png', 'rb') as f:
                    data = f.read()
                os.remove('/tmp/screen_cap.png')
                return data
        except:
            pass
        return None

    def start(self):
        self.running = True
        server = socketserver.TCPServer(('', self.port), self.VNCHandler)
        server.allow_reuse_address = True
        self.server = server
        print(f'VNC server listening on port {self.port}')
        server.serve_forever()

    class VNCHandler(socketserver.BaseRequestHandler):
        def handle(self):
            global libvnc
            print(f'VNC client connected from {self.client_address}')
            # Simple VNC handshake - just send desktop name
            self.request.send(b'RFB 003.008\n')

if __name__ == '__main__':
    server = VNCServer(port=5900)
    server.start()