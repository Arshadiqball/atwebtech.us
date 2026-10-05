#!/usr/bin/env python3
"""Local site server: static files plus a small admin API for contact details and journal posts."""

import hashlib
import json
import re
import secrets
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlparse

ROOT = Path(__file__).resolve().parent
DATA = ROOT / "data"
CONTENT_PATH = DATA / "content.json"
AUTH_PATH = DATA / "auth.json"
HOST = "127.0.0.1"
PORT = 4173
DEFAULT_PASSWORD = "atwebtech"
SESSION_SECONDS = 60 * 60 * 12

write_lock = threading.Lock()
sessions = {}

BLOCKED_PREFIXES = ("/data/", "/server.py", "/package.json")


def hash_password(password, salt=None):
    if salt is None:
        salt = secrets.token_hex(16)
    digest = hashlib.pbkdf2_hmac("sha256", password.encode(), bytes.fromhex(salt), 200_000).hex()
    return {"salt": salt, "hash": digest}


def password_ok(password, record):
    check = hash_password(password, record["salt"])
    return secrets.compare_digest(check["hash"], record["hash"])


def ensure_auth():
    DATA.mkdir(exist_ok=True)
    if AUTH_PATH.exists():
        return
    AUTH_PATH.write_text(json.dumps(hash_password(DEFAULT_PASSWORD), indent=2))


def load_content():
    return json.loads(CONTENT_PATH.read_text())


def save_content(content):
    DATA.mkdir(exist_ok=True)
    tmp = CONTENT_PATH.with_suffix(".json.tmp")
    tmp.write_text(json.dumps(content, indent=2) + "\n")
    tmp.replace(CONTENT_PATH)


def public_content(content):
    posts = [post for post in content.get("posts", []) if post.get("published")]
    posts.sort(key=lambda post: post.get("date", ""), reverse=True)
    return {"contact": content.get("contact", {}), "posts": posts}


def slugify(text):
    slug = re.sub(r"[^a-z0-9]+", "-", (text or "").lower()).strip("-")
    return slug[:80] or "note"


def unique_slug(posts, slug, ignore_id=None):
    taken = {post["slug"] for post in posts if post.get("id") != ignore_id}
    base = slug
    n = 2
    while slug in taken:
        slug = f"{base}-{n}"
        n += 1
    return slug


def clean_text(value, limit):
    text = " ".join(str(value or "").split())
    return text[:limit]


def normalize_post(raw, posts, existing=None):
    title = clean_text(raw.get("title"), 140)
    if not title:
        raise ValueError("Add a title.")
    body = str(raw.get("body") or "").replace("\r\n", "\n").strip()
    if len(body) < 20:
        raise ValueError("Write a little more in the body.")
    if len(body) > 20000:
        raise ValueError("That note is too long.")
    excerpt = clean_text(raw.get("excerpt"), 280)
    if not excerpt:
        excerpt = clean_text(body, 180)
    category = clean_text(raw.get("category"), 40) or "Notes"
    date = str(raw.get("date") or "").strip()
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", date):
        raise ValueError("Use a date like 2026-10-05.")
    requested = slugify(raw.get("slug") or title)
    slug = unique_slug(posts, requested, None if existing is None else existing.get("id"))
    return {
        "id": existing["id"] if existing else secrets.token_hex(8),
        "slug": slug,
        "title": title,
        "excerpt": excerpt,
        "category": category,
        "date": date,
        "body": body,
        "published": bool(raw.get("published", True)),
    }


def normalize_contact(raw):
    email = clean_text(raw.get("email"), 120)
    phone = clean_text(raw.get("phone"), 40)
    address = clean_text(raw.get("address"), 160)
    if not re.fullmatch(r"[^@\s]+@[^@\s]+\.[^@\s]+", email):
        raise ValueError("Enter a valid email address.")
    digits = re.sub(r"\D", "", phone)
    if len(digits) < 10:
        raise ValueError("Enter a phone number with at least 10 digits.")
    if len(address) < 3:
        raise ValueError("Enter an address.")
    return {
        "email": email,
        "phone": phone,
        "phoneHref": "tel:+" + digits,
        "address": address,
    }


class Handler(BaseHTTPRequestHandler):
    server_version = "ATwebTech/1.0"

    def log_message(self, fmt, *args):
        print("[%s] %s" % (self.log_date_time_string(), fmt % args))

    def send_json(self, code, payload, extra_headers=None):
        body = json.dumps(payload).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        if extra_headers:
            for key, value in extra_headers:
                self.send_header(key, value)
        self.end_headers()
        self.wfile.write(body)

    def read_json(self):
        length = int(self.headers.get("Content-Length", "0") or "0")
        if length > 1_000_000:
            raise ValueError("That request is too large.")
        raw = self.rfile.read(length) if length else b"{}"
        try:
            data = json.loads(raw.decode() or "{}")
        except json.JSONDecodeError as exc:
            raise ValueError("The form data was not valid.") from exc
        if not isinstance(data, dict):
            raise ValueError("The form data was not valid.")
        return data

    def same_origin(self):
        origin = self.headers.get("Origin")
        if not origin:
            return True
        host = self.headers.get("Host", "")
        return origin == f"http://{host}" or origin == f"https://{host}"

    def session_token(self):
        cookie = self.headers.get("Cookie", "")
        for part in cookie.split(";"):
            name, _, value = part.strip().partition("=")
            if name == "at_session" and value in sessions:
                return value
        return None

    def require_admin(self):
        if not self.same_origin():
            self.send_json(403, {"error": "Open the admin on this computer."})
            return False
        if not self.session_token():
            self.send_json(401, {"error": "Sign in again."})
            return False
        return True

    def do_GET(self):
        path = unquote(urlparse(self.path).path)
        if path == "/api/content":
            self.send_json(200, public_content(load_content()))
            return
        if path == "/api/session":
            self.send_json(200, {"ok": bool(self.session_token())})
            return
        if path == "/api/admin/content":
            if not self.require_admin():
                return
            content = load_content()
            posts = sorted(content.get("posts", []), key=lambda post: post.get("date", ""), reverse=True)
            self.send_json(200, {"contact": content.get("contact", {}), "posts": posts})
            return
        self.serve_static(path)

    def do_POST(self):
        path = unquote(urlparse(self.path).path)
        try:
            if path == "/api/login":
                self.login()
                return
            if path == "/api/logout":
                token = self.session_token()
                if token:
                    sessions.pop(token, None)
                self.send_json(200, {"ok": True}, [("Set-Cookie", "at_session=; HttpOnly; Path=/; SameSite=Lax; Max-Age=0")])
                return
            if path == "/api/admin/posts":
                if not self.require_admin():
                    return
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    post = normalize_post(raw, content["posts"])
                    content["posts"].append(post)
                    save_content(content)
                self.send_json(201, {"post": post})
                return
            if path == "/api/admin/password":
                if not self.require_admin():
                    return
                raw = self.read_json()
                record = json.loads(AUTH_PATH.read_text())
                current = str(raw.get("current") or "")
                nxt = str(raw.get("next") or "")
                if not password_ok(current, record):
                    self.send_json(400, {"error": "The current password does not match."})
                    return
                if len(nxt) < 8:
                    self.send_json(400, {"error": "Use at least 8 characters."})
                    return
                AUTH_PATH.write_text(json.dumps(hash_password(nxt), indent=2))
                self.send_json(200, {"ok": True})
                return
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})
            return
        self.send_json(404, {"error": "Not found."})

    def do_PUT(self):
        path = unquote(urlparse(self.path).path)
        try:
            if path == "/api/admin/contact":
                if not self.require_admin():
                    return
                contact = normalize_contact(self.read_json())
                with write_lock:
                    content = load_content()
                    content["contact"] = contact
                    save_content(content)
                self.send_json(200, {"contact": contact})
                return
            prefix = "/api/admin/posts/"
            if path.startswith(prefix):
                if not self.require_admin():
                    return
                post_id = path[len(prefix):]
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    existing = next((post for post in content["posts"] if post["id"] == post_id), None)
                    if not existing:
                        self.send_json(404, {"error": "That note is gone."})
                        return
                    updated = normalize_post(raw, content["posts"], existing)
                    content["posts"] = [updated if post["id"] == post_id else post for post in content["posts"]]
                    save_content(content)
                self.send_json(200, {"post": updated})
                return
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})
            return
        self.send_json(404, {"error": "Not found."})

    def do_DELETE(self):
        path = unquote(urlparse(self.path).path)
        prefix = "/api/admin/posts/"
        if not path.startswith(prefix):
            self.send_json(404, {"error": "Not found."})
            return
        if not self.require_admin():
            return
        post_id = path[len(prefix):]
        with write_lock:
            content = load_content()
            before = len(content["posts"])
            content["posts"] = [post for post in content["posts"] if post["id"] != post_id]
            if len(content["posts"]) == before:
                self.send_json(404, {"error": "That note is gone."})
                return
            save_content(content)
        self.send_json(200, {"ok": True})

    def login(self):
        if not self.same_origin():
            self.send_json(403, {"error": "Open the admin on this computer."})
            return
        raw = self.read_json()
        record = json.loads(AUTH_PATH.read_text())
        if not password_ok(str(raw.get("password") or ""), record):
            self.send_json(401, {"error": "That password is not right."})
            return
        token = secrets.token_urlsafe(32)
        sessions[token] = True
        self.send_json(
            200,
            {"ok": True},
            [("Set-Cookie", f"at_session={token}; HttpOnly; Path=/; SameSite=Lax; Max-Age={SESSION_SECONDS}")],
        )

    def serve_static(self, path):
        if path != "/" and any(path == item.rstrip("/") or path.startswith(item) for item in BLOCKED_PREFIXES):
            self.send_error(404)
            return
        if path in ("/blog", "/blog/"):
            self._send_file(ROOT / "blog" / "index.html")
            return
        if path.startswith("/blog/") and path != "/blog/index.html":
            self._send_file(ROOT / "blog" / "index.html")
            return
        if path in ("/admin", "/admin/"):
            self._send_file(ROOT / "admin" / "index.html")
            return
        rel = "index.html" if path == "/" else path.lstrip("/")
        file_path = (ROOT / rel).resolve()
        try:
            file_path.relative_to(ROOT)
        except ValueError:
            self.send_error(404)
            return
        if file_path.is_dir():
            file_path = file_path / "index.html"
        if not file_path.is_file():
            self.send_error(404)
            return
        self._send_file(file_path)

    def _send_file(self, file_path):
        data = file_path.read_bytes()
        content_type = {
            ".html": "text/html; charset=utf-8",
            ".css": "text/css; charset=utf-8",
            ".js": "text/javascript; charset=utf-8",
            ".json": "application/json; charset=utf-8",
            ".png": "image/png",
            ".jpg": "image/jpeg",
            ".jpeg": "image/jpeg",
            ".webp": "image/webp",
            ".gif": "image/gif",
            ".svg": "image/svg+xml",
            ".ico": "image/x-icon",
            ".woff2": "font/woff2",
        }.get(file_path.suffix.lower(), "application/octet-stream")
        self.send_response(200)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(data)))
        if file_path.suffix.lower() in {".html", ".js", ".css"} or "blog" in str(file_path) or "admin" in str(file_path):
            self.send_header("Cache-Control", "no-cache")
        self.end_headers()
        self.wfile.write(data)


def main():
    ensure_auth()
    if not CONTENT_PATH.exists():
        raise SystemExit(f"Missing {CONTENT_PATH}")
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"ATwebTech site  http://{HOST}:{PORT}/")
    print(f"Journal         http://{HOST}:{PORT}/blog/")
    print(f"Admin           http://{HOST}:{PORT}/admin/")
    httpd.serve_forever()


if __name__ == "__main__":
    main()
