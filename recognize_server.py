"""
HTTP server giữ model YOLO trong RAM để nhận diện nhanh.

Chạy:
  python recognize_server.py
  hoặc start_ocr_server_silent.vbs (ẩn)

http://127.0.0.1:8765/health
POST /recognize  {"image": "C:/path/to.jpg"}
"""
import atexit
import json
import os
import sys
import traceback
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
if BASE_DIR not in sys.path:
    sys.path.insert(0, BASE_DIR)

from recognize import load_models, recognize_plate  # noqa: E402

HOST = os.environ.get("NDBS_OCR_HOST", "127.0.0.1")
PORT = int(os.environ.get("NDBS_OCR_PORT", "8766"))
PID_FILE = os.path.join(BASE_DIR, "storage", "framework", "ocr_server.pid")


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def log_message(self, fmt, *args):
        sys.stderr.write("[%s] %s\n" % (self.log_date_time_string(), fmt % args))

    def _send_json(self, code, payload):
        body = json.dumps(payload, ensure_ascii=True).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path == "/health":
            self._send_json(200, {"ok": True, "service": "ndbs-recognize", "pid": os.getpid()})
            return
        if parsed.path == "/recognize":
            qs = parse_qs(parsed.query)
            image = (qs.get("image") or [None])[0]
            self._recognize(image)
            return
        self._send_json(404, {"success": False, "error": "Not found"})

    def do_POST(self):
        parsed = urlparse(self.path)
        if parsed.path != "/recognize":
            self._send_json(404, {"success": False, "error": "Not found"})
            return

        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) if length > 0 else b"{}"
        image = None
        try:
            data = json.loads(raw.decode("utf-8") or "{}")
            image = data.get("image")
        except Exception:
            image = raw.decode("utf-8", errors="ignore").strip()

        self._recognize(image)

    def _recognize(self, image):
        if not image:
            self._send_json(400, {"success": False, "error": "Missing image path"})
            return
        if not os.path.exists(image):
            self._send_json(404, {"success": False, "error": f"Image file not found: {image}"})
            return
        try:
            result = recognize_plate(image)
            self._send_json(200 if result.get("success") else 422, result)
        except Exception as e:
            self._send_json(500, {
                "success": False,
                "error": str(e),
                "trace": traceback.format_exc(),
            })


def _write_pid():
    try:
        os.makedirs(os.path.dirname(PID_FILE), exist_ok=True)
        with open(PID_FILE, "w", encoding="utf-8") as f:
            f.write(str(os.getpid()))
    except Exception:
        pass


def _clear_pid():
    try:
        if os.path.exists(PID_FILE):
            os.remove(PID_FILE)
    except Exception:
        pass


def main():
    print(f"Loading YOLO models from {BASE_DIR} ...", flush=True)
    load_models(BASE_DIR)
    print("Models ready.", flush=True)

    try:
        server = ThreadingHTTPServer((HOST, PORT), Handler)
    except OSError as e:
        print(f"Port {PORT} dang duoc dung hoac khong bind duoc: {e}", flush=True)
        print("Neu OCR cu dang chay, khong can start them.", flush=True)
        sys.exit(0)

    server.allow_reuse_address = True
    _write_pid()
    atexit.register(_clear_pid)

    print(f"NDBS recognize server listening on http://{HOST}:{PORT}", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopping...", flush=True)
    finally:
        server.server_close()
        _clear_pid()


if __name__ == "__main__":
    main()
