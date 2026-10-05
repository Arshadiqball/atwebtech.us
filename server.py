#!/usr/bin/env python3
"""Local site server: static files plus a small admin API for contact details and journal posts."""

import datetime
import hashlib
import json
import os
import re
import secrets
import smtplib
import ssl
import threading
from email.message import EmailMessage
from email.utils import formataddr
from zoneinfo import ZoneInfo
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlparse

ROOT = Path(__file__).resolve().parent
DATA = ROOT / "data"
CONTENT_PATH = DATA / "content.json"
AUTH_PATH = DATA / "auth.json"
MESSAGES_PATH = DATA / "messages.json"
MAIL_PATH = DATA / "mail.json"
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


def load_messages():
    if not MESSAGES_PATH.exists():
        return []
    return json.loads(MESSAGES_PATH.read_text())


def save_messages(messages):
    DATA.mkdir(exist_ok=True)
    MESSAGES_PATH.write_text(json.dumps(messages, indent=2) + "\n")


EMAIL_RE = re.compile(r"[^@\s]+@[^@\s]+\.[^@\s]+")
TUCSON = ZoneInfo("America/Phoenix")


def mail_defaults():
    return {
        "host": "",
        "port": 587,
        "user": "",
        "password": "",
        "from": "arshad@atwebtechnologies.com",
        "fromName": "At Web Technologies",
        "to": "arshadiqbal.d@gmail.com, aisal@atwebtechnologies.com",
        "tls": "starttls",
    }


def split_emails(value):
    parts = []
    for piece in str(value or "").replace(";", ",").split(","):
        email = piece.strip()
        if email:
            parts.append(email)
    return parts


def tucson_now():
    now = datetime.datetime.now(TUCSON)
    hour = now.strftime("%I").lstrip("0") or "12"
    return f"{now.strftime('%B')} {now.day}, {now.year}, {hour}:{now.strftime('%M %p')} MST"


def load_mail():
    settings = mail_defaults()
    if MAIL_PATH.exists():
        stored = json.loads(MAIL_PATH.read_text())
        if isinstance(stored, dict):
            settings.update(stored)
    env_map = {
        "host": "SMTP_HOST",
        "port": "SMTP_PORT",
        "user": "SMTP_USER",
        "password": "SMTP_PASSWORD",
        "from": "SMTP_FROM",
        "to": "SMTP_TO",
        "tls": "SMTP_TLS",
    }
    for key, name in env_map.items():
        value = os.environ.get(name)
        if value:
            settings[key] = int(value) if key == "port" else value
    return settings


def public_mail(settings):
    shown = {key: settings.get(key) for key in ("host", "port", "user", "from", "fromName", "to", "tls")}
    shown["passwordSet"] = bool(settings.get("password"))
    return shown


def normalize_mail(raw, previous):
    host = clean_text(raw.get("host"), 200)
    user = clean_text(raw.get("user"), 200)
    sender = clean_text(raw.get("from") or raw.get("sender"), 200)
    from_name = clean_text(raw.get("fromName") or raw.get("from_name") or previous.get("fromName"), 80)
    recipients = split_emails(clean_text(raw.get("to"), 400))
    tls = clean_text(raw.get("tls") or raw.get("encryption"), 20) or "starttls"
    if tls not in ("starttls", "ssl"):
        raise ValueError("Choose STARTTLS or SSL.")
    try:
        port = int(raw.get("port") or 587)
    except (TypeError, ValueError):
        raise ValueError("Enter a mail port.")
    if port < 1 or port > 65535:
        raise ValueError("Enter a mail port.")
    if host and not re.fullmatch(r"[A-Za-z0-9.-]+", host):
        raise ValueError("Enter the mail server host.")
    if sender and not EMAIL_RE.fullmatch(sender):
        raise ValueError("Enter a valid From address.")
    if not recipients or any(not EMAIL_RE.fullmatch(email) for email in recipients):
        raise ValueError("Enter the addresses that should receive requests.")
    password = str(raw.get("password") or raw.get("pass") or "")
    if not password:
        password = previous.get("password", "")
    if len(password) > 200:
        raise ValueError("That mail password is too long.")
    tls_name = clean_text(raw.get("tlsName") or previous.get("tlsName"), 200)
    if tls_name and not re.fullmatch(r"[A-Za-z0-9.-]+", tls_name):
        raise ValueError("Enter the certificate host.")
    saved = {
        "host": host,
        "port": port,
        "user": user,
        "password": password,
        "from": sender or "arshad@atwebtechnologies.com",
        "fromName": from_name or "At Web Technologies",
        "to": ", ".join(recipients),
        "tls": tls,
    }
    if tls_name:
        saved["tlsName"] = tls_name
    return saved


def send_consultation(entry):
    if entry.get("kind") != "contact":
        return False, ""
    settings = load_mail()
    if not settings.get("host") or not settings.get("password"):
        return False, "Add the mailbox under Admin → Mail."
    recipients = split_emails(settings.get("to"))
    if not recipients:
        return False, "Add at least one address under Admin → Mail."
    name = " ".join(part for part in (entry.get("firstName"), entry.get("lastName")) if part).strip()
    message = EmailMessage()
    message["Subject"] = f"New project inquiry from {name or entry['email']}"
    message["From"] = formataddr((settings.get("fromName") or "At Web Technologies", settings["from"]))
    message["To"] = ", ".join(recipients)
    message["Reply-To"] = formataddr((name, entry["email"])) if name else entry["email"]
    message.set_content(
        "\n".join([
            "New consultation request",
            "ATwebTech · Tucson, Arizona",
            "",
            f"Name: {name or '—'}",
            f"Email: {entry['email']}",
            f"Phone: {entry.get('phone') or '—'}",
            f"Plan: {((entry.get('plan') or '').title() + (' yearly' if entry.get('billing') == 'yearly' else ' monthly')) if entry.get('plan') else '—'}",
            f"Received: {entry['at']}",
            "",
            "Project",
            entry.get("message") or "",
            "",
            "Reply to this email to reach the sender.",
        ])
    )
    try:
        if settings.get("tls") == "ssl":
            with smtplib.SMTP_SSL(settings["host"], int(settings["port"]), timeout=20, context=ssl.create_default_context()) as smtp:
                if settings.get("user"):
                    smtp.login(settings["user"], settings["password"])
                smtp.send_message(message)
        else:
            with smtplib.SMTP(settings["host"], int(settings["port"]), timeout=20) as smtp:
                smtp.ehlo()
                # The host certificate is issued to tlsName, not the mail hostname.
                if settings.get("tlsName"):
                    smtp._host = settings["tlsName"]
                smtp.starttls(context=ssl.create_default_context())
                smtp.ehlo()
                if settings.get("user"):
                    smtp.login(settings["user"], settings["password"])
                smtp.send_message(message)
    except (OSError, smtplib.SMTPException) as exc:
        detail = str(exc).strip() or "The mail server refused the message."
        return False, detail[:180]
    return True, ""


def normalize_message(raw):
    kind = raw.get("kind")
    email = clean_text(raw.get("email"), 120)
    if kind not in ("contact", "newsletter"):
        raise ValueError("That form could not be read.")
    if not EMAIL_RE.fullmatch(email):
        raise ValueError("Enter a valid email address.")
    entry = {
        "id": secrets.token_hex(6),
        "kind": kind,
        "email": email,
        "at": tucson_now(),
    }
    if kind == "contact":
        first = clean_text(raw.get("firstName"), 80)
        last = clean_text(raw.get("lastName"), 80)
        phone = clean_text(raw.get("phone"), 40)
        message = " ".join(str(raw.get("message") or "").split())
        if not first:
            raise ValueError("Add your first name.")
        digits = re.sub(r"\D", "", phone)
        if phone and not (len(digits) == 10 or (len(digits) == 11 and digits.startswith("1"))):
            raise ValueError("Enter a US phone number with the area code.")
        if "privacy" in raw and raw.get("privacy") is not True:
            raise ValueError("Agree to the privacy policy to continue.")
        if len(message) < 5:
            raise ValueError("Describe the project in a sentence or two.")
        if len(message) > 4000:
            raise ValueError("That message is too long.")
        plan = clean_text(raw.get("plan"), 20).lower()
        cycle = clean_text(raw.get("billing"), 20).lower()
        entry.update({"firstName": first, "lastName": last, "phone": phone, "message": message})
        if plan in ("starter", "pro", "enterprise"):
            entry["plan"] = plan
            entry["billing"] = "yearly" if cycle == "yearly" else "monthly"
    return entry


def public_content(content):
    posts = [post for post in content.get("posts", []) if post.get("published")]
    posts.sort(key=lambda post: post.get("date", ""), reverse=True)
    services = [service for service in content.get("services", []) if service.get("published", True)]
    services.sort(key=lambda service: (service.get("order", 0), service.get("title", "")))
    projects = [public_project(project) for project in content.get("projects", []) if project.get("published", True)]
    projects.sort(key=lambda project: (project.get("order", 0), project.get("title", "")))
    return {"contact": content.get("contact", {}), "posts": posts, "services": services, "projects": projects}


def public_project(project):
    return {
        "id": project.get("id"),
        "title": project.get("title", ""),
        "category": project.get("category", ""),
        "src": project.get("image", ""),
        "aspect": 0.75,
        "description": project.get("description", ""),
        "metrics": project.get("metrics") or [],
        "highlights": project.get("highlights") or [],
        "techStack": project.get("techStack") or [],
        "order": project.get("order", 0),
    }


def projects_of(content):
    projects = content.get("projects")
    if not isinstance(projects, list):
        content["projects"] = []
    return content["projects"]


def image_ok(image):
    if image.startswith("https://") and " " not in image and ".." not in image:
        return True
    return bool(re.fullmatch(r"/[A-Za-z0-9_./-]+", image)) and ".." not in image


def lines_of(value):
    if isinstance(value, list):
        return value
    return str(value or "").splitlines()


def services_of(content):
    services = content.get("services")
    if not isinstance(services, list):
        content["services"] = []
    return content["services"]


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


def normalize_service(raw, services, existing=None):
    title = clean_text(raw.get("title"), 80)
    if not title:
        raise ValueError("Add a service name.")
    summary = clean_text(raw.get("summary"), 320)
    if len(summary) < 12:
        raise ValueError("Add a short summary.")
    body = str(raw.get("body") or "").replace("\r\n", "\n").strip()
    if len(body) < 20:
        raise ValueError("Write a little more in the detail.")
    if len(body) > 20000:
        raise ValueError("That service page is too long.")
    image = clean_text(raw.get("image"), 180)
    if not re.fullmatch(r"/[A-Za-z0-9_./-]+", image) or ".." in image:
        raise ValueError("Use an image path like /services/ai_chatbots.jpg.")
    accent = clean_text(raw.get("accent"), 7) or "#67e8f9"
    if not re.fullmatch(r"#[0-9A-Fa-f]{6}", accent):
        raise ValueError("Use a color like #67e8f9.")
    try:
        order = int(raw.get("order") if raw.get("order") not in ("", None) else (existing or {}).get("order", len(services) + 1))
    except (TypeError, ValueError):
        raise ValueError("Order should be a number.")
    source = raw.get("points")
    if isinstance(source, list):
        lines = source
    else:
        lines = str(source or "").splitlines()
    points = []
    for line in lines:
        point = clean_text(line, 160)
        if point:
            points.append(point)
    if len(points) > 8:
        raise ValueError("Keep the list to 8 points.")
    slug = unique_slug(services, slugify(raw.get("slug") or title), None if existing is None else existing.get("id"))
    return {
        "id": existing["id"] if existing else secrets.token_hex(8),
        "slug": slug,
        "title": title,
        "summary": summary,
        "image": image,
        "accent": accent.lower(),
        "order": order,
        "points": points,
        "body": body,
        "published": bool(raw.get("published", True)),
    }


def normalize_project(raw, projects, existing=None):
    title = clean_text(raw.get("title"), 140)
    if not title:
        raise ValueError("Add a project name.")
    category = clean_text(raw.get("category"), 80)
    if len(category) < 2:
        raise ValueError("Add a category.")
    description = " ".join(str(raw.get("description") or "").split())
    if len(description) < 20:
        raise ValueError("Add a short description.")
    if len(description) > 2000:
        raise ValueError("That description is too long.")
    image = str(raw.get("image") or "").strip()
    if len(image) > 400 or not image_ok(image):
        raise ValueError("Use an image path like /images/projects/project-agents.jpg, or an https image address.")
    try:
        order = int(raw.get("order") if raw.get("order") not in ("", None) else (existing or {}).get("order", len(projects) + 1))
    except (TypeError, ValueError):
        raise ValueError("Order should be a number.")
    metrics = []
    for line in lines_of(raw.get("metrics")):
        text = " ".join(str(line).split())
        if not text:
            continue
        if "|" not in text:
            raise ValueError("Write each metric as a value, a vertical bar, and a label.")
        value, label = text.split("|", 1)
        value, label = value.strip(), label.strip()
        if not value or not label:
            raise ValueError("Each metric needs a value and a label.")
        metrics.append({"value": value[:24], "label": label[:40]})
    if not metrics or len(metrics) > 6:
        raise ValueError("Add between 1 and 6 metrics.")
    highlights = [clean_text(line, 180) for line in lines_of(raw.get("highlights"))]
    highlights = [line for line in highlights if line]
    if not highlights or len(highlights) > 8:
        raise ValueError("Add between 1 and 8 capabilities.")
    tech = []
    for line in lines_of(raw.get("techStack") if raw.get("techStack") is not None else raw.get("tech")):
        for part in str(line).split(","):
            name = clean_text(part, 40)
            if name:
                tech.append(name)
    if not tech or len(tech) > 12:
        raise ValueError("Add between 1 and 12 technologies.")
    return {
        "id": existing["id"] if existing else secrets.token_hex(4),
        "title": title,
        "category": category,
        "image": image,
        "description": description,
        "metrics": metrics,
        "highlights": highlights,
        "techStack": tech,
        "order": order,
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
        if path == "/api/admin/messages":
            if not self.require_admin():
                return
            self.send_json(200, {"messages": list(reversed(load_messages()))})
            return
        if path == "/api/admin/mail":
            if not self.require_admin():
                return
            self.send_json(200, {"mail": public_mail(load_mail())})
            return
        if path == "/api/admin/content":
            if not self.require_admin():
                return
            content = load_content()
            posts = sorted(content.get("posts", []), key=lambda post: post.get("date", ""), reverse=True)
            services = sorted(services_of(content), key=lambda service: (service.get("order", 0), service.get("title", "")))
            projects = sorted(projects_of(content), key=lambda project: (project.get("order", 0), project.get("title", "")))
            self.send_json(200, {"contact": content.get("contact", {}), "posts": posts, "services": services, "projects": projects})
            return
        self.serve_static(path)

    def do_POST(self):
        path = unquote(urlparse(self.path).path)
        try:
            if path == "/api/messages":
                if not self.same_origin():
                    self.send_json(403, {"error": "Open the site on this computer."})
                    return
                entry = normalize_message(self.read_json())
                emailed, mail_error = send_consultation(entry)
                entry["emailed"] = emailed
                if mail_error:
                    entry["mailError"] = mail_error
                with write_lock:
                    messages = load_messages()
                    messages.append(entry)
                    save_messages(messages[-200:])
                self.send_json(201, {"ok": True, "emailed": emailed, "mailError": mail_error})
                return
            if path == "/api/login":
                self.login()
                return
            if path == "/api/logout":
                token = self.session_token()
                if token:
                    sessions.pop(token, None)
                self.send_json(200, {"ok": True}, [("Set-Cookie", "at_session=; HttpOnly; Path=/; SameSite=Lax; Max-Age=0")])
                return
            if path == "/api/admin/projects":
                if not self.require_admin():
                    return
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    project = normalize_project(raw, projects_of(content))
                    content["projects"].append(project)
                    save_content(content)
                self.send_json(201, {"project": project})
                return
            if path == "/api/admin/services":
                if not self.require_admin():
                    return
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    service = normalize_service(raw, services_of(content))
                    content["services"].append(service)
                    save_content(content)
                self.send_json(201, {"service": service})
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
            if path == "/api/admin/mail":
                if not self.require_admin():
                    return
                with write_lock:
                    settings = normalize_mail(self.read_json(), load_mail())
                    MAIL_PATH.write_text(json.dumps(settings, indent=2) + "\n")
                self.send_json(200, {"mail": public_mail(settings)})
                return
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
            project_prefix = "/api/admin/projects/"
            if path.startswith(project_prefix):
                if not self.require_admin():
                    return
                project_id = path[len(project_prefix):]
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    existing = next((project for project in projects_of(content) if project["id"] == project_id), None)
                    if not existing:
                        self.send_json(404, {"error": "That project is gone."})
                        return
                    updated = normalize_project(raw, content["projects"], existing)
                    content["projects"] = [updated if project["id"] == project_id else project for project in content["projects"]]
                    save_content(content)
                self.send_json(200, {"project": updated})
                return
            service_prefix = "/api/admin/services/"
            if path.startswith(service_prefix):
                if not self.require_admin():
                    return
                service_id = path[len(service_prefix):]
                raw = self.read_json()
                with write_lock:
                    content = load_content()
                    existing = next((service for service in services_of(content) if service["id"] == service_id), None)
                    if not existing:
                        self.send_json(404, {"error": "That service is gone."})
                        return
                    updated = normalize_service(raw, content["services"], existing)
                    content["services"] = [updated if service["id"] == service_id else service for service in content["services"]]
                    save_content(content)
                self.send_json(200, {"service": updated})
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
        project_prefix = "/api/admin/projects/"
        if path.startswith(project_prefix):
            if not self.require_admin():
                return
            project_id = path[len(project_prefix):]
            with write_lock:
                content = load_content()
                before = len(projects_of(content))
                content["projects"] = [project for project in content["projects"] if project["id"] != project_id]
                if len(content["projects"]) == before:
                    self.send_json(404, {"error": "That project is gone."})
                    return
                save_content(content)
            self.send_json(200, {"ok": True})
            return
        service_prefix = "/api/admin/services/"
        if path.startswith(service_prefix):
            if not self.require_admin():
                return
            service_id = path[len(service_prefix):]
            with write_lock:
                content = load_content()
                before = len(services_of(content))
                content["services"] = [service for service in content["services"] if service["id"] != service_id]
                if len(content["services"]) == before:
                    self.send_json(404, {"error": "That service is gone."})
                    return
                save_content(content)
            self.send_json(200, {"ok": True})
            return
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
        if path in ("/services", "/services/") or (
            path.startswith("/services/") and not (ROOT / path.lstrip("/")).resolve().is_file()
        ):
            candidate = (ROOT / path.lstrip("/")).resolve()
            try:
                candidate.relative_to(ROOT)
            except ValueError:
                self.send_error(404)
                return
            self._send_file(ROOT / "service" / "index.html")
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
        if path in ("/contact", "/contact/"):
            self._send_file(ROOT / "contact" / "index.html")
            return
        if path in ("/privacy", "/privacy/"):
            self._send_file(ROOT / "privacy" / "index.html")
            return
        if path in ("/terms", "/terms/"):
            self._send_file(ROOT / "terms" / "index.html")
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
            missing = ROOT / "404.html"
            if missing.is_file():
                self._send_file(missing, 404)
            else:
                self.send_error(404)
            return
        self._send_file(file_path)

    def _send_file(self, file_path, status=200):
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
        self.send_response(status)
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
    print(f"Contact         http://{HOST}:{PORT}/contact/")
    print(f"Admin           http://{HOST}:{PORT}/admin/")
    httpd.serve_forever()


if __name__ == "__main__":
    main()
