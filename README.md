# Secure Blog CMS (SQL-Free, File-Based)

Secure Blog CMS is a security-first PHP blogging platform that stores content in JSON files instead of a database. It includes a full admin UI, comment moderation, image uploads, and a Resilience Center for static exports and IPFS pinning.

## Features

### Publishing
- File-based storage (no SQL/database required)
- Drafts and published posts, slugs, excerpts, and pagination
- Optional search, private posts, and password-protected posts
- RSS feed generation
- Image uploads with server-side security checks

### Admin and Users
- Admin dashboard for posts, comments, users, and settings
- Roles: admin, editor, author
- Comment moderation (pending/approved/spam/trash)
- Backups and restore from the admin UI

### Resilience Center
- Static site export (HTML + RSS) for static hosting
- ZIP bundles for easy distribution
- Optional auto-pinning to IPFS via Pinata
- Export bundles stored in `data/exports/`

### Security
- CSRF protection on all forms (single-use tokens, no replay)
- XSS sanitization and output escaping (DOM-based HTML purification)
- CSP headers enabled by default, plus standard HTTP security headers
- Rate limiting (login, comments, uploads, short URLs, post passwords)
- Account lockout after failed login attempts
- Session hardening with IP + User-Agent fingerprinting
- Security event logging to `data/logs/`
- Mandatory SHA-256 checksums on upgrades; auto-upgrade disabled for safety
- Proxy header spoofing protection (Cloudflare/X-Forwarded headers gated behind config toggle)
- COOP/CORP security headers for cross-origin isolation
- Permissions-Policy header to restrict browser APIs
- HSTS with preload and includeSubDomains (respects proxy headers)
- Comment author name sanitization (strip tags, length limit, email validation)
- Site URL validation (scheme whitelist prevents javascript: and data: URLs)
- Per-user daily upload rate limiting (50/day per user)
- Post password brute-force protection (5 attempts per IP per 5 minutes)

### Built-In Updater
- Check for updates from the admin panel
- Download and verify files with SHA-256 integrity checks
- Automatic backup before upgrade
- Config file never overwritten during updates
- One-click upgrade process

## Requirements

- PHP 7.4+ (PHP 8.x recommended)
- Web server (Apache/Nginx) or PHP built-in server
- Write access to the `data/` directory
- Extensions (optional but recommended):
  - `curl` for Pinata IPFS pinning and in-app updates
  - `zip` for export ZIP bundles
  - `dom` for DOM-based HTML sanitization (fallback regex available)

## Quick Start

### Option A: Installer (recommended)
1. Copy the project into your web root.
2. Ensure the `data/` directory is writable by the web server.
3. Visit `/install/index.php` in your browser and complete the wizard.
4. After install, keep `data/installed.lock` in place (re-install requires deleting it).
5. **Delete the `install/` directory after installation for best security.**

### Option B: Manual install
1. Copy the project into your web root.
2. Ensure the `data/` directory is writable by the web server.
3. Generate an Argon2id password hash (use single quotes to avoid `$` interpretation):

```bash
cat > /tmp/hashpass.php << 'EOF'
<?php
echo password_hash('YourSecurePassword123!', PASSWORD_ARGON2ID) . PHP_EOL;
EOF
php /tmp/hashpass.php
```

4. Update `includes/config.php` with your credentials (use **single quotes** for the hash):

```php
define('ADMIN_USERNAME', 'your_username');
define('ADMIN_PASSWORD_HASH', '$argon2id$v=19$m=65536,...');  // single quotes!
```

5. Update site settings in `includes/config.php` or the admin UI.
6. Open `/admin.php` and log in.

> ⚠️ **Important:** Always use single quotes around `ADMIN_PASSWORD_HASH`. Argon2id hashes contain `$` characters which PHP interprets as variable references inside double-quoted strings, corrupting the hash and breaking login.

### Reverse Proxy / Cloudflare Setup

If your CMS is behind Cloudflare or a reverse proxy that sets `X-Forwarded-For` or `CF-Connecting-IP` headers, enable trusted proxy mode in `includes/config.php`:

```php
define('TRUST_PROXY_HEADERS', true);
```

**Warning:** Only enable this when actually behind a trusted proxy. Enabling it without a proxy allows IP spoofing and session bypass.

## Configuration

### Site settings (recommended)
Most settings are managed in the admin UI at `/admin/settings.php` and stored in:

- `data/settings/site.json`

These settings override defaults from `includes/config.php`.

### hCaptcha (comments)
Comments can require hCaptcha. Configure via environment variables (recommended):

- `HCAPTCHA_SITEKEY`
- `HCAPTCHA_SECRET`

Or set `hcaptcha_sitekey` in `data/settings/site.json` and keep the secret in env.

### Pinata (IPFS)
Configure Pinata credentials in `/admin/settings.php` to enable auto-pinning of exports.

## Updating

### In-app updater
- Go to `/admin/upgrade.php` and check for updates.
- The upgrader downloads `update/manifest.json` from the configured update source.
- **All file updates require SHA-256 checksum verification** — no file is written without integrity verification.
- **`includes/config.php` is never overwritten** — your credentials and settings are preserved.
- Auto-upgrade has been disabled for security. All upgrades must be manually triggered.

### Manual update
1. **Backup `data/` and `includes/config.php`.**
2. Replace application files with the new release.
3. **Do NOT overwrite `includes/config.php`** — preserve your existing credentials and settings.
4. Re-check your settings and log in to confirm.

### Creating a release
For maintainers, use the `generate_manifest.sh` script to prepare updates:

```bash
cd update/
./generate_manifest.sh 1.6.0 "Description of changes"
# Then commit, tag, and push:
git add update/ && git commit -m "v1.6.0: update manifest"
git tag -a v1.6.0 -m "v1.6.0"
git push origin main --tags
```

## Project Layout

```
secure-blog-cms/
  admin/            Admin UI (posts, comments, users, settings, resilience, upgrade)
  data/             JSON data storage (posts, users, comments, logs, backups)
  includes/         Core classes (Security, Storage, Comments, Resilience, Uploads)
  install/          Installation wizard (delete after install)
  templates/        Public templates
  update/           Update packages, manifest, and release files
  index.php         Public homepage
  post.php          Single post view
  rss.php           RSS feed
  s.php             Short URL redirect handler
```

## Deployment Notes

### Nginx (recommended)
A sample nginx config is included as `nginx.conf` with:
- Pretty URL rewrites (WordPress-style `/post/slug/`, `/category/tech/`, etc.)
- Security deny rules for `data/`, `includes/`, and `install/` directories
- Static file caching headers

### CloudPanel / Varnish
When deploying behind CloudPanel with Varnish:
1. Set `TRUST_PROXY_HEADERS` to `true` in `includes/config.php`
2. Add nginx deny rules for `data/`, `includes/`, and `install/` directories
3. Ensure parent directory permissions are `755` (CloudPanel may reset to `770`)
4. Delete the `install/` directory after setup

### Important: Config Protection
- `includes/config.php` contains your admin credentials and site settings
- **Never overwrite it during updates** — the updater skips it automatically
- `includes/config.php.example` is provided as a reference template
- When deploying manually, always exclude `includes/config.php` from file copies
- The `ADMIN_PASSWORD_HASH` must use **single quotes** (not double quotes) to prevent PHP from interpreting `$` in Argon2id hashes

## Changelog

### v1.5.5 — Category/Tag Management & Branding (2026-07-14)

**New Features:**
- **Category & Tag Deletion** — Delete categories and tags with automatic cleanup of post references
- **Slug Collision Resolution** — Auto-appends `-2`, `-3`, etc. when a slug already exists
- **Duplicate Prevention** — Case-insensitive name matching rejects exact duplicates; slug collisions auto-resolved

**Improvements:**
- No-cache headers on admin categories page for CSRF token freshness behind Cloudflare/Varnish
- Branding updated to Digital Systems LLC / AfterPacket
- Removed duplicate version display in public footer
- Admin categories page now shows post count per category/tag

**Bug Fixes:**
- Fixed PHP syntax error in `addCategory()` return statement
- Fixed delete confirmation dialog quoting issues
- Fixed CSRF token invalidation on category/tag management page

### v1.5.4 — Install Cleanup & Update Channels (2026-07-14)

**Critical Bug Fixes:**
- **[CRITICAL]** Admin session logout on idle — session fingerprint validation was destroying sessions when IP or User-Agent shifted between requests behind Cloudflare/Varnish proxies. Now logs a warning and updates the fingerprint instead of destroying the session.
- **[CRITICAL]** Argon2id password hash corruption — hashes containing `$` characters were corrupted by PHP variable interpolation in double-quoted strings, causing admin login failures. `ADMIN_PASSWORD_HASH` now uses single quotes.
- **[CRITICAL]** Updater "Requested version not found in manifest" error — Upgrader required exact version string match. Now uses the manifest version directly and only checks that it's newer than the current version. Update cache also cleared before upgrading.

**Improvements:**
- Session regenerate interval increased from 30 minutes to 4 hours (less disruption for users)
- `config.php` has prominent DO NOT OVERWRITE warning header
- Added `config.php.example` as install template — installer copies from example if config.php doesn't exist
- `includes/config.php` removed from update manifest — updater will never overwrite it
- Built-in updater now has real SHA-256 hashes for integrity verification
- Added `generate_manifest.sh` script for maintainers to generate release manifests

### v1.5.2 — Config Protection (2026-07-14)

**Critical Bug Fix:**
- **[CRITICAL]** Password hash corruption fix (same root cause as v1.5.3, addressed in config.php template and installer)

**Improvements:**
- Added `config.php.example` as install template
- Installer copies from config.php.example; uses single-quote replacement for password hash
- Removed config.php from update manifest

### v1.5.1 — Pen Test Hardening (2026-07-14)

**Security Fixes:**
- **[MEDIUM]** Rate limiting added to post password attempts (5 per IP per 5 minutes) — prevents brute-force attacks against password-protected posts.
- **[MEDIUM]** Session fingerprint now hashes User-Agent with SHA-256 — strengthens session binding beyond IP-only, format: `sha256(ip | sha256(user_agent))`.
- **[MEDIUM]** Site URL setting validation added — `filter_var(FILTER_VALIDATE_URL)` and scheme whitelist (`http`/`https`) prevent open redirect and XSS via malicious URL values.
- **[MEDIUM]** Cross-Origin isolation headers added — `Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Resource-Policy: same-origin` prevent cross-origin information leakage.
- **[MEDIUM]** Comment author name sanitization — HTML tags stripped, length capped at 100 characters. Email validated when provided.
- **[MEDIUM]** Per-user daily upload rate limit added (50 uploads/day per user) alongside existing per-IP hourly limit.

### v1.5.0 — Security + Subfolder Install Fix (2026-07-13)

**Security Fixes:**
- **[HIGH]** Removed CSRF token from image upload URL query string — tokens were being logged in server access logs and browser history. Now sent only via `X-CSRF-Token` header and POST body.
- **[HIGH]** Removed `data:` from public CSP `img-src` — prevents SVG-based XSS through `data:image/svg+xml` URIs. Admin CSP still allows `data:` for TinyMCE paste compatibility.
- **[HIGH]** HSTS header now respects `TRUST_PROXY_HEADERS` — previously only checked `$_SERVER["HTTPS"]`, which is empty behind Cloudflare/Varnish. Sites behind proxies now correctly send HSTS.
- **[MEDIUM]** Short URL redirect (`s.php`) changed from 301 to 302 — prevents browser cache poisoning if target changes.
- **[MEDIUM]** Short URL redirect now validates resolved slug corresponds to a published post — prevents open redirect.
- **[MEDIUM]** Post password hashing upgraded from bcrypt to Argon2id with fallback.
- **[MEDIUM]** Removed `ini_set()` calls for `allow_url_fopen`/`allow_url_include` — these are `PHP_INI_SYSTEM` directives. Added comments for php.ini configuration.
- **[LOW]** Removed debug `error_log()` from `ImageUpload.php`.
- **[LOW]** Fixed `Content-Disposition` filename escaping in `serve-image.php`.

**Bug Fixes:**
- **[CRITICAL]** All internal links now use `cms_path()` — fixes broken pagination, search, admin links, RSS, and comment forms in subfolder installs.
- **[CRITICAL]** Fixed `index.php` line 469 — missing `?>` closing tag caused 500 parse error on PHP 8.x.

### v1.4.1 — Patch Release (2026-07-13)

**Security Fixes:**
- **[HIGH]** Password input fields for post protection were `type="text"` — changed to `type="password"`.
- **[HIGH]** `ENABLE_UPLOAD_MALWARE_SCAN` was `false` by default — changed to `true`.
- **[MEDIUM]** Session cookie `secure` flag now respects `TRUST_PROXY_HEADERS`.
- **[MEDIUM]** Removed debug `console.log` statements from create/edit post pages.

**Bug Fixes:**
- Image URL insertion: TinyMCE `valid_elements` now allows `class` and `style` on `<img>`.
- Image paste: Enabled `paste_data_images: true` in TinyMCE.
- RSS self-link: Now uses `cms_path()` for correct URLs in subfolder installs.

### v1.4.0 — Security Hardening Release (2026-07-13)

**Critical Fixes:**
- **[CRITICAL]** Removed Remote Code Execution vector in upgrade system — `download_url` no longer accepted from POST data. Upgrades use `performUpgradeFromManifest()` with hardcoded manifest URL. SHA-256 checksums mandatory. Auto-upgrade disabled.
- **[CRITICAL]** Added credential placeholder detection — warns if `REPLACE_ME_*` defaults are still in place.

**High Fixes:**
- **[HIGH]** Replaced regex XSS sanitizer with DOM-based HTML purification (DOMDocument + XPath). Regex fallback for servers without `dom` extension.
- **[HIGH]** CSP headers enabled by default.
- **[HIGH]** CSRF tokens now single-use for all forms — removed `image_upload`/`edit_post_form` reuse exception.
- **[HIGH]** Session fingerprint gated behind `TRUST_PROXY_HEADERS` config (default: `false`).
- **[HIGH]** Removed version disclosure header (`X-SecureBlogCMS-Version`) — only sent if `SHOW_VERSION_HEADER` is explicitly `true`.
- **[HIGH]** Password protection enforced on public pages. Private posts hidden from listings, search, and RSS.

**Medium Fixes:**
- Role validation whitelist enforced in `addUser()` and `updateUser()`.
- Password hashing unified to Argon2id across all user management.
- Rate limiting on comment submissions (3/IP/hour).
- Install directory `.htaccess` hardened.
- CORS credentials set to `false` on image endpoints.
- Debug logging reduced in upload endpoint.
- Error reporting hardened to `E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE`.

---

Version: 1.5.5  
Last Updated: 2026-07-14  
Created by: Digital Systems LLC / AfterPacket
Security Level: High