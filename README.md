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
- Rate limiting (login, comments, uploads, short URLs) and account lockout
- Session hardening and regeneration with IP binding
- Security event logging to `data/logs/`
- Mandatory SHA-256 checksums on upgrades; auto-upgrade disabled for safety
- Proxy header spoofing protection (Cloudflare/X-Forwarded headers gated behind config toggle)

## Requirements

- PHP 7.4+ (PHP 8.x recommended)
- Web server (Apache/Nginx) or PHP built-in server
- Write access to the `data/` directory
- Extensions (optional but recommended):
  - `curl` for Pinata IPFS pinning
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
3. Set your admin password hash in `includes/config.php`:

```php
// Generate a new hash
// php -r "echo password_hash('YourSecurePassword123!', PASSWORD_ARGON2ID);"

// Then update:
define('ADMIN_PASSWORD_HASH', 'your_generated_hash_here');
```

4. Update basic site settings in `includes/config.php`:

```php
define('SITE_NAME', 'Your Blog Name');
define('SITE_DESCRIPTION', 'Your blog description');
define('SITE_URL', 'https://yourdomain.com');
```

5. Open `/admin.php` and log in.

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

## Usage

### Create and edit posts
- Log in at `/admin.php` and use the Create/Edit screens.
- Images are uploaded via the editor and stored in `data/uploads/images/`.

### Comments
- Public comments are stored in `data/comments/`.
- Moderate in `/admin/comments.php`.

### Backups
- Backups are created automatically on key actions (if enabled).
- Manual backup/restore is available in the admin dashboard.

### Resilience Center (static export)
- Go to `/admin/resilience.php` and generate a static bundle.
- Bundles are stored in `data/exports/` and may include a ZIP.
- Static exports do not include dynamic features like comments, search, or private post access.

## Updating

### In-app updater
- Go to `/admin/upgrade.php` and check for updates.
- The upgrader downloads `update/manifest.json` from the configured update source.
- **All file updates require SHA-256 checksum verification** — no file is written without integrity verification.
- Auto-upgrade has been disabled for security. All upgrades must be manually triggered.

### Manual update
1. Backup `data/` and `includes/config.php`.
2. Replace application files with the new release.
3. Re-check your settings and log in to confirm.

## Project Layout (high level)

```
secure-blog-cms/
  admin/            Admin UI (posts, comments, users, settings, resilience, upgrade)
  data/             JSON data storage (posts, users, comments, logs, backups)
  includes/         Core classes (Security, Storage, Comments, Resilience, Uploads)
  install/          Installation wizard (delete after install)
  templates/        Public templates
  tools/            Build and release helpers
  update/           Update packages and manifest
  index.php         Public homepage
  post.php          Single post view
  rss.php           RSS feed
  s.php             Short URL redirect handler
```

## Changelog

### v1.4.0 — Security Hardening Release (2026-07-13)

**Critical Fixes:**
- **[CRITICAL]** Removed Remote Code Execution vector in upgrade system — `download_url` is no longer accepted from POST data. Upgrades now use `performUpgradeFromManifest()` which only fetches from the hardcoded manifest URL. SHA-256 checksums are mandatory for all files (no `"auto"` bypass). Auto-upgrade disabled by default.
- **[CRITICAL]** Added credential placeholder detection — CMS warns if default `REPLACE_ME_*` credentials are still in place after installation.

**High Fixes:**
- **[HIGH]** Replaced regex-based XSS sanitizer with DOM-based HTML purification using `DOMDocument` + XPath. Removes all `on*` event handlers, `formaction`, `javascript:`/`data:` URIs, and dangerous tags (`<svg>`, `<math>`, `<iframe>`, `<object>`, `<embed>`, etc.). Regex fallback preserved for servers without `dom` extension.
- **[HIGH]** CSP headers now enabled by default (`ENABLE_CSP_HEADERS = true`).
- **[HIGH]** CSRF tokens are now single-use for all forms — removed `image_upload`/`edit_post_form` reuse exception that allowed replay attacks within the 48-hour token lifetime. Upload handler now returns a fresh token on success for seamless multi-image uploads.
- **[HIGH]** Session fingerprint no longer blindly trusts `HTTP_CF_CONNECTING_IP` or `HTTP_X_FORWARDED_FOR` headers. New `TRUST_PROXY_HEADERS` config constant (default: `false`) must be explicitly enabled for Cloudflare/reverse proxy deployments. HTTPS detection via `X-Forwarded-Proto`/`CF_Visitor` similarly gated.
- **[HIGH]** Removed version disclosure header (`X-SecureBlogCMS-Version`) — now only sent if `SHOW_VERSION_HEADER` is explicitly defined and `true`.
- **[HIGH]** Password protection is now enforced on public pages. Password-protected posts require a password to view content (session-based unlock with 1-hour TTL). Private posts are hidden from non-authenticated users in listings, search, and RSS.

**Medium Fixes:**
- **[MEDIUM]** Role validation added — `addUser()` and `updateUser()` now enforce whitelist (`admin`, `editor`, `author`). Arbitrary roles are rejected.
- **[MEDIUM]** Password hashing unified to Argon2id across all user management (`Users` class previously used `PASSWORD_DEFAULT`/bcrypt; now uses `PASSWORD_ARGON2ID` with bcrypt fallback).
- **[MEDIUM]** Rate limiting added to comment submissions (3 comments per IP per hour).
- **[MEDIUM]** Install directory `.htaccess` hardened — blocks sensitive file types.
- **[MEDIUM]** CORS `Access-Control-Allow-Credentials` changed from `true` to `false` on image upload/serve endpoints.
- **[MEDIUM]** Debug logging reduced in upload endpoint — removed verbose `$_POST`/`$_FILES` key dumps.
- **[MEDIUM]** Error reporting hardened from `E_ALL` to `E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE`.

**Low Fixes:**
- **[LOW]** Consistent IP source across all rate limiting (login, comments, uploads, short URLs) via new `Security::getClientIPPublic()` method.
- **[LOW]** Index page redirect logic improved to not redirect to installer when CMS is already installed.
- **[LOW]** Password-protected posts show 🔒 indicator in listings and RSS feed; content is hidden until password is entered.

---

Version: 1.4.0
Last Updated: 2026-07-13
Security Level: High