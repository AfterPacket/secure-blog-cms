# Secure Blog CMS v1.5.2 — Release Notes

**Release Date:** 2026-07-14  
**Severity:** Critical Bug Fix  
**GitHub:** https://github.com/AfterPacket/secure-blog-cms

---

## Summary

This release fixes a critical bug where Argon2id password hashes containing `$` characters were corrupted by PHP variable interpolation when stored in double-quoted strings. This caused admin login failures. The password hash define now uses single quotes to prevent this. It also prevents config.php from being overwritten during updates, and adds config.php.example as an install template.

All users should upgrade.

---

## Bug Fixes

### 🔴 Critical: Password Hash Corruption
Argon2id hashes contain `$` characters (e.g., `$argon2id$v=19$m=...`). When stored in a double-quoted PHP string like `define("ADMIN_PASSWORD_HASH", "$argon2id...")`, PHP interprets the `$` as variable references, corrupting the hash and preventing login. Fixed by using single quotes: `define('ADMIN_PASSWORD_HASH', '...')`.

### 🟡 Config Protection
- `config.php` now has a prominent header warning not to overwrite it during updates
- Added `config.php.example` as a template for new installations
- Installer copies from `config.php.example` to `config.php` if it doesn't exist
- Upgrader already skips `config.php` — now also removed from update manifest

### 🟡 Update Manifest Updated
- Manifest updated to v1.5.2
- `includes/config.php` removed from manifest file list

---

## Upgrade Instructions

1. Replace all application files with the new release.
2. **DO NOT overwrite `includes/config.php`** — preserve your existing credentials and settings.
3. If upgrading from a fresh install, the installer will use `config.php.example` as a template.
4. No data migration required (file-based storage).

---

## File Changes

- `includes/config.php` — Password hash define now uses single quotes; added DO NOT OVERWRITE warning header
- `includes/config.php.example` — New template file for installations
- `install/index.php` — Installer now copies from config.php.example; uses single-quote replacement for password hash
- `update/manifest.json` — Updated to v1.5.2; removed config.php from file list
- `data/version.json` — Version bump to 1.5.2

---

# Secure Blog CMS v1.5.1 — Release Notes

**Release Date:** 2026-07-14  
**Severity:** Security Hardening  
**GitHub:** https://github.com/AfterPacket/secure-blog-cms

---

## Summary

This release addresses findings from a penetration test, adding rate limiting to post password attempts, strengthening session fingerprinting with User-Agent hashing, validating the site URL setting, adding Cross-Origin isolation headers, sanitizing comment author names, and adding per-user daily upload limits.

All users should upgrade.

---

## Security Fixes

### 🟡 Rate Limiting Added to Post Password Attempts
Post password attempts are now rate-limited to 5 per IP per 5 minutes, preventing brute-force attacks against password-protected posts.

### 🟡 Session Fingerprint Strengthened with User-Agent Hashing
Session fingerprints now hash the User-Agent string with SHA-256 before including it, instead of using the raw value. The fingerprint format is now `sha256(ip | sha256(user_agent))`, preventing session hijacking when the browser changes.

### 🟡 Site URL Validation Added to Settings
The `site_url` setting is now validated as a proper URL with `http` or `https` scheme, preventing open redirect or XSS via malicious URL values.

### 🟡 Cross-Origin Isolation Headers Added
`Cross-Origin-Opener-Policy: same-origin` and `Cross-Origin-Resource-Policy: same-origin` headers are now sent on all responses, preventing cross-origin information leakage.

### 🟡 Comment Author Name Sanitization
Comment author names are now stripped of HTML tags and limited to 100 characters. Email addresses are validated when provided.

### 🟡 Per-User Daily Upload Rate Limit Added
A per-user daily limit of 50 uploads has been added alongside the existing per-IP hourly limit, preventing abuse by authenticated users.

---

## Upgrade Instructions

1. Replace all application files with the new release.
2. No database or data migration required (file-based storage).
3. Verify your `includes/config.php` settings are preserved.

---

## File Changes

- `post.php` — Added rate limiting before post password verification
- `includes/Security.php` — Session fingerprint now hashes User-Agent; added COOP/CORP headers
- `admin/settings.php` — Added URL validation for `site_url`
- `includes/comments.php` — Strip HTML tags and limit author name length; validate email
- `admin/upload-image.php` — Added per-user daily upload rate limit (50/day)
- `includes/config.php` — Version bump to 1.5.1
- `data/version.json` — Version bump to 1.5.1

---

# Secure Blog CMS v1.5.0 — Release Notes

**Release Date:** 2026-07-13  
**Severity:** Important Security + Subfolder Install Fix  
**GitHub:** https://github.com/AfterPacket/secure-blog-cms

---

## Summary

This release fixes critical broken links in subfolder installs (e.g. `/blog/`), removes the CSRF token from upload URL query strings (which was leaking into server logs), upgrades HSTS detection for proxy deployments, and adds post validation to short URL redirects.

All users should upgrade, especially those running the CMS in a subfolder.

---

## Security Fixes

### 🔴 CSRF Token Leaked in Server Logs via Image Upload URL
The TinyMCE image upload handler appended the CSRF token to the URL query string (`?csrf_token=...&v=...`). This meant tokens appeared in nginx access logs, browser history, and `Referer` headers. The token is now sent exclusively via the `X-CSRF-Token` header and POST body.

**Files changed:** `admin/create-post.php`, `admin/edit-post.php`, `admin/upload-image.php`

---

### 🟠 `data:` URIs Removed from Public CSP `img-src`
The public-facing Content Security Policy included `data:` in `img-src`, which could allow SVG-with-JavaScript XSS via `data:image/svg+xml` URIs. Removed from the public CSP. The admin CSP retains `data:` for TinyMCE paste compatibility.

**Files changed:** `includes/config.php`

---

### 🟠 HSTS Header Not Sent Behind Cloudflare/Varnish
The HSTS header only checked `$_SERVER["HTTPS"] === "on"`, which is empty when behind a reverse proxy. Now uses the same `TRUST_PROXY_HEADERS` logic as the rest of the app.

**Files changed:** `includes/Security.php`

---

### 🟡 Short URL Redirect — 301 Changed to 302
`s.php` used `301 Moved Permanently`, which browsers cache permanently. Changed to `302 Found` to prevent cache poisoning if a short URL target changes.

**Files changed:** `s.php`

---

### 🟡 Short URL Redirect — Post Existence Validation Added
`s.php` now validates that the resolved slug corresponds to an actual published post before redirecting. Previously, a manipulated `short-urls.json` could redirect to an arbitrary path.

**Files changed:** `s.php`

---

### 🟡 Post Password Hashing Upgraded to Argon2id
Post passwords were hashed with `PASSWORD_DEFAULT` (bcrypt). Now uses `PASSWORD_ARGON2ID` with a bcrypt fallback for consistency with user account hashing.

**Files changed:** `includes/Storage.php`

---

### 🟡 `allow_url_fopen`/`allow_url_include` Runtime Override Removed
These are `PHP_INI_SYSTEM` directives and cannot be changed via `ini_set()`. The calls were silently failing. Replaced with comments explaining they must be set in `php.ini` or `php-fpm.conf`.

**Files changed:** `includes/config.php`

---

## Bug Fixes

### 🔴 All Internal Links Now Use `cms_path()` — Subfolder Installs Fixed
Multiple links throughout `index.php`, `post.php`, `s.php`, and `rss.php` used hardcoded relative paths (`admin/admin.php`, `?page=2`, `rss.php`) that broke when the CMS was installed in a subfolder like `/blog/`. All internal links now use `cms_path()` for correct URL generation.

**Files changed:** `index.php`, `post.php`, `s.php`

---

### 🔴 `index.php` Line 469 Parse Error Fixed
A missing `?>` closing tag on `<?php if (!empty($tagSlug) && $currentTag):` caused a PHP 8.x parse error (500 status). This was the root cause of the front-end 500 error on lassiter.eu.

**Files changed:** `index.php` (fixed in v1.4.1, included here for completeness)

---

### 🟡 Debug Logging Removed from Production
`error_log("DEBUG: ...")` statements in `ImageUpload.php` and `upload-image.php` were logging upload step details and CSRF token presence in production.

**Files changed:** `includes/ImageUpload.php`, `admin/upload-image.php`

---

### 🟡 Duplicate `X-Content-Type-Options` Header Removed
`serve-image.php` sent `X-Content-Type-Options: nosniff` twice. Deduplicated.

**Files changed:** `admin/serve-image.php`

---

### 🟡 `Content-Disposition` Escaping Fixed
`serve-image.php` used `addslashes()` for the filename in the `Content-Disposition` header. `addslashes()` is not the correct HTTP header escaping function. Replaced with `basename()`.

**Files changed:** `admin/serve-image.php`

---

## Upgrade Instructions

1. **Backup your `data/` directory and `includes/config.php`**
2. Replace all application files with the v1.5.0 release
3. If you use Cloudflare or a reverse proxy, ensure `define('TRUST_PROXY_HEADERS', true);` is set in `includes/config.php`
4. Verify your site works correctly
5. **Delete the `install/` directory** for best security practice

## File Changes

| File | Change |
|------|--------|
| `includes/config.php` | Version → 1.5.0, removed `data:` from public CSP `img-src`, removed ineffective `ini_set` calls, added `ALLOW_URL_IMAGES` constant |
| `includes/Security.php` | HSTS respects `TRUST_PROXY_HEADERS` |
| `includes/Storage.php` | Post password hashing → `PASSWORD_ARGON2ID` with fallback |
| `includes/ImageUpload.php` | Removed debug logging statements |
| `admin/upload-image.php` | Removed CSRF token from URL query string, removed debug logging |
| `admin/serve-image.php` | Removed duplicate header, fixed `Content-Disposition` escaping |
| `admin/create-post.php` | Removed CSRF token from upload URL query string |
| `admin/edit-post.php` | Removed CSRF token from upload URL query string |
| `index.php` | All links use `cms_path()`, search form action fixed, pagination links fixed, RSS link fixed, admin link fixed |
| `post.php` | All links use `cms_path()`, edit link fixed, comment form action fixed, password form action fixed |
| `s.php` | Added post existence validation, 301→302 redirect, all links use `cms_path()`, added Storage dependency |
| `data/version.json` | Version → 1.5.0 |
| `nginx.conf` | Added `install` to deny rules, updated comments |

---

# Secure Blog CMS v1.4.1 — Release Notes

**Release Date:** 2026-07-13  
**Severity:** Patch Release (Security + Bug Fixes)  
**GitHub:** https://github.com/AfterPacket/secure-blog-cms

---

## Summary

This patch release fixes security issues missed in the v1.4.0 audit: plaintext password inputs, disabled malware scanning on uploads, session cookie security behind proxies, and debug logging in production. It also fixes TinyMCE image URL insertion and RSS self-link for subfolder installs.

All users on v1.4.0 should upgrade.

---

## Security Fixes

### 🔴 Password Inputs Were Plaintext (`type="text"`)
Post password inputs in both `create-post.php` and `edit-post.php` used `type="text"` instead of `type="password"`. Passwords were visible on screen and leaked via the DOM's `value` attribute. Changed to `type="password"` with `autocomplete="new-password"`.

**Files changed:** `admin/create-post.php`, `admin/edit-post.php`

---

### 🔴 Malware Scan Disabled by Default
`ENABLE_UPLOAD_MALWARE_SCAN` was set to `false` in `config.php`, meaning the backdoor detection in `ImageUpload.php` was completely bypassed. Changed to `true`.

**Files changed:** `includes/config.php`

---

### 🟠 Session `Secure` Cookie Flag Ignored Proxy Headers
The session cookie `secure` flag only checked `$_SERVER["HTTPS"] === "on"`. Behind Cloudflare or reverse proxies with `TRUST_PROXY_HEADERS` enabled, cookies were set without the `Secure` flag even on HTTPS connections, allowing session cookies to be sent over plain HTTP.

**Fix:** The `secure` flag now respects the same `TRUST_PROXY_HEADERS` logic used elsewhere in the app, checking `X-Forwarded-Proto` and `CF-Visitor` headers.

**Files changed:** `includes/Security.php`

---

### 🟡 Debug Logging in Production
`console.log` statements in `create-post.php` and `edit-post.php` were logging CSRF tokens, upload responses, and build version to the browser console in production.

**Files changed:** `admin/create-post.php`, `admin/edit-post.php`

---

## Bug Fixes

### 🟡 TinyMCE Image URL Insertion Stripped Attributes
The `valid_elements` config restricted `<img>` to `src|alt|title|width|height|loading`, stripping `class` and `style` from URL-based images. Added `class` and `style` to allowed attributes.

**Files changed:** `admin/create-post.php`, `admin/edit-post.php`

---

### 🟡 Image Paste Disabled
`paste_data_images` was set to `false`, preventing pasted images from being uploaded. Changed to `true`.

**Files changed:** `admin/create-post.php`, `admin/edit-post.php`

---

### 🟡 RSS Self-Link Wrong for Subfolder Installs
The `<atom:link rel="self">` in `rss.php` used `SITE_URL . "/rss.php"` instead of `SITE_URL . cms_path("rss.php")`, producing incorrect self-links when the CMS is installed in a subfolder.

**Files changed:** `rss.php`

---

## Upgrade Instructions

1. **Backup your `data/` directory and `includes/config.php`**
2. Replace all application files with the v1.4.1 release
3. If you use Cloudflare or a reverse proxy, ensure `define('TRUST_PROXY_HEADERS', true);` is set in `includes/config.php`
4. Verify your site works correctly
5. **Delete the `install/` directory** for best security practice

## File Changes

| File | Change |
|------|--------|
| `includes/config.php` | Version → 1.4.1, `ENABLE_UPLOAD_MALWARE_SCAN` → `true` |
| `includes/Security.php` | Session `secure` flag now respects `TRUST_PROXY_HEADERS` |
| `admin/create-post.php` | Password field → `type="password"`, removed debug logs, TinyMCE img attrs fix, paste enabled |
| `admin/edit-post.php` | Password field → `type="password"`, removed debug logs, TinyMCE img attrs fix, paste enabled |
| `rss.php` | Self-link uses `cms_path()` for subfolder installs |
| `data/version.json` | Version → 1.4.1 |
| `data/settings/version.json` | Version → 1.4.1 |

---

# Secure Blog CMS v1.4.0 — Release Notes

**Release Date:** 2026-07-13  
**Severity:** Critical Security Hardening Release  
**GitHub:** https://github.com/AfterPacket/secure-blog-cms

---

## Summary

This is a **critical security hardening release** that addresses a remote code execution vulnerability in the auto-upgrade system, fixes multiple XSS bypass vectors, eliminates CSRF token replay attacks, hardens proxy header handling, enforces password protection on posts, and adds defense-in-depth across the entire application.

All users should upgrade immediately.

---

## Critical Fixes

### 🔴 RCE via Upgrade System (CVE Pending)
The upgrade endpoint accepted a `download_url` parameter from POST data, allowing an authenticated admin to inject a malicious URL and achieve remote code execution. Auto-upgrade also executed code from remote manifests without mandatory checksum verification.

**Fix:** Removed `download_url` from POST parameters entirely. Introduced `performUpgradeFromManifest()` that only uses the hardcoded manifest URL. SHA-256 checksums are now mandatory for all files — the `"auto"` bypass has been eliminated. Auto-upgrade has been disabled as a security precaution and returns an error message directing users to upgrade manually.

**Files changed:** `includes/Upgrader.php`, `admin/upgrade.php`

---

### 🔴 Default Placeholder Credentials
The CMS could run with default `REPLACE_ME_USERNAME` / `REPLACE_ME_PASSWORD_HASH` credentials if the installer was bypassed. While the installer replaces these, there was no runtime enforcement.

**Fix:** Added runtime detection of placeholder credentials in `includes/config.php`. A warning is logged and future versions can enforce a hard block.

**Files changed:** `includes/config.php`

---

## High Fixes

### 🟠 XSS Bypass via HTML Content Sanitization
The `removeXSSAttributes()` method used regex patterns that could be bypassed through attribute value encoding, whitespace injection, and mixed-case event handlers. Tags like `<svg>`, `<math>`, `<iframe>`, `<object>`, and `<embed>` were not stripped.

**Fix:** Replaced regex-based sanitizer with a DOM-based approach using `DOMDocument` + `DOMXPath`. All `on*` event handler attributes are now removed via XPath (`//@*[starts-with(name(), "on")]`), dangerous tags are stripped entirely, and `javascript:`/`data:` URIs in `href`/`src` are neutralized. A regex fallback is preserved for servers without the `dom` extension.

**Files changed:** `includes/Security.php`

---

### 🟠 CSP Headers Disabled by Default
Content Security Policy headers were disabled (`ENABLE_CSP_HEADERS = false`), leaving no browser-level XSS mitigation layer.

**Fix:** Changed default to `true` in `includes/config.php`.

**Files changed:** `includes/config.php`

---

### 🟠 CSRF Token Replay Attack
CSRF tokens for `image_upload` and `edit_post_form` forms were never consumed after validation, allowing replay within the 48-hour token lifetime. An attacker who intercepted a token could reuse it indefinitely.

**Fix:** All CSRF tokens are now single-use. The `image_upload` / `edit_post_form` exceptions have been removed from `validateCSRFToken()`.

**Files changed:** `includes/Security.php`

---

### 🟠 Proxy Header Spoofing (IP / HTTPS)
The session fingerprint blindly trusted `HTTP_CF_CONNECTING_IP` and `HTTP_X_FORWARDED_FOR` headers for IP detection, and `HTTP_X_FORWARDED_PROTO` / `HTTP_CF_VISITOR` for HTTPS detection. An attacker not behind Cloudflare could spoof these headers to bypass session binding and force insecure cookies.

**Fix:** Introduced `TRUST_PROXY_HEADERS` config constant (default: `false`). The new `getClientIP()` / `getClientIPPublic()` methods only read proxy headers when this is explicitly enabled. HTTPS detection similarly gated.

**Files changed:** `includes/config.php`, `includes/Security.php`, `admin/login.php`, `admin/upload-image.php`, `includes/comments.php`, `s.php`

---

### 🟠 Version Disclosure Header
The `X-SecureBlogCMS-Version` header was sent on every response, aiding attacker reconnaissance.

**Fix:** Disabled by default. Only sent if `SHOW_VERSION_HEADER` is explicitly defined and `true`.

**Files changed:** `includes/Security.php`

---

## Medium Fixes

### 🟡 Role Validation Missing
User creation and update accepted any arbitrary role string (e.g., `"superadmin"`, `"root"`) without validation.

**Fix:** Added `private static $validRoles = ['admin', 'editor', 'author']` whitelist enforced in `addUser()` and `updateUser()`.

**Files changed:** `includes/users.php`

---

### 🟡 Password Hashing Inconsistency
`Users::addUser()` used `PASSWORD_DEFAULT` (bcrypt) while `Security::hashPassword()` used Argon2id, creating weaker hashes for secondary users.

**Fix:** Both methods now use `PASSWORD_ARGON2ID` with explicit cost parameters, with a bcrypt fallback for servers without Argon2id support.

**Files changed:** `includes/users.php`

---

### 🟡 No Rate Limiting on Comment Submission
The `addComment()` method had no rate limiting, allowing unlimited spam submissions.

**Fix:** Added rate limit of 3 comments per IP per hour using `Security::checkRateLimit()`.

**Files changed:** `includes/comments.php`

---

### 🟡 Unprotected Install Directory
The `install/` directory `.htaccess` allowed all access and had no file-type restrictions.

**Fix:** Updated `.htaccess` to block sensitive file types. Improved `index.php` to not redirect to installer when CMS is already installed.

**Files changed:** `install/.htaccess`, `index.php`

---

### 🟡 CORS Credentials Header
Image upload and serve endpoints set `Access-Control-Allow-Credentials: true`, potentially enabling credential theft in specific attack scenarios.

**Fix:** Changed to `Access-Control-Allow-Credentials: false`.

**Files changed:** `admin/upload-image.php`, `admin/serve-image.php`

---

### 🟡 Debug Logging in Upload Endpoint
The upload endpoint logged `$_POST` keys, `$_FILES` keys, and file metadata on every request.

**Fix:** Reduced to a single informational line. Security-relevant logging (CSRF failures, upload results) preserved.

**Files changed:** `admin/upload-image.php`

---

### 🟡 Error Reporting Too Verbose
`error_reporting(E_ALL)` was set in production, generating verbose logs with deprecation and strict notices.

**Fix:** Changed to `E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE`.

**Files changed:** `includes/config.php`

---

## High Fixes (Additional)

### 🟠 Password Protection Not Enforced
Password-protected posts could be viewed without a password. The `password_protected` and `post_password` fields were saved to disk but never checked on the public-facing pages. Any visitor could read the full content of any password-protected post, and the content also appeared in search results and RSS feeds.

**Fix:** Added password gate in `post.php` with session-based unlock (TTL-based, 1-hour expiry via `POST_PASSWORD_TTL`). Private posts (visibility=private) are now hidden from non-authenticated users in listings, search, and RSS. Password-protected posts show a 🔒 indicator in listings and hide content behind a password form. Search and RSS exclude content of password-protected posts for non-authenticated users.

**Files changed:** `post.php`, `index.php`, `rss.php`, `includes/Storage.php`, `templates/index_template.php`, `includes/config.php`

---

## Medium Fixes (Additional)

### 🟡 Image Upload CSRF Token Breaks Multi-Image Uploads
After v1.4.0 made CSRF tokens single-use, the TinyMCE image upload handler would consume the token on the first upload. Every subsequent upload would fail with 403, requiring a round-trip to get a new token before retrying. This caused poor UX where every other image upload failed.

**Fix:** The upload endpoint now returns a `new_token` in the success response. The TinyMCE handlers in `create-post.php` and `edit-post.php` refresh their CSRF token from the success response, eliminating the failed round-trip.

**Files changed:** `admin/upload-image.php`, `admin/create-post.php`, `admin/edit-post.php`

---

## Low Fixes

### 🔵 Consistent IP Source for Rate Limiting
Rate limiting across login, comments, uploads, and short URLs used inconsistent IP sources (`$_SERVER["REMOTE_ADDR"]` vs `HTTP_CF_CONNECTING_IP`).

**Fix:** All rate limiting now uses `Security::getClientIPPublic()`, which respects the `TRUST_PROXY_HEADERS` setting.

**Files changed:** `admin/login.php`, `admin/upload-image.php`, `includes/comments.php`, `s.php`

---

## Upgrade Instructions

1. **Backup your `data/` directory and `includes/config.php`**
2. Replace all application files with the v1.4.0 release
3. If you use Cloudflare or a reverse proxy, add `define('TRUST_PROXY_HEADERS', true);` to your `includes/config.php` (already present, defaults to `false`)
4. Verify your site works correctly
5. **Delete the `install/` directory** for best security practice

## File Changes

| File | Change |
|------|--------|
| `includes/config.php` | CSP enabled, error reporting hardened, proxy toggle, credential check, `ALLOW_PRIVATE_POSTS`/`ALLOW_PASSWORD_PROTECTED` constants |
| `includes/Security.php` | DOM-based XSS sanitizer, single-use CSRF tokens, IP method, version header toggle |
| `includes/users.php` | Role validation whitelist, Argon2id hashing |
| `includes/comments.php` | Rate limiting on submissions |
| `includes/Storage.php` | Filter private posts from listings, limit search on password-protected posts |
| `includes/Upgrader.php` | RCE fix: manifest-only upgrades, mandatory checksums, disabled auto-upgrade |
| `admin/upgrade.php` | Removed download_url from POST, uses performUpgradeFromManifest() |
| `admin/upload-image.php` | CORS fix, debug logging reduced, consistent IP, return fresh CSRF token on success |
| `admin/serve-image.php` | CORS fix |
| `admin/login.php` | Consistent IP for rate limiting |
| `s.php` | Consistent IP for rate limiting |
| `index.php` | Hide private posts, show 🔒 on password-protected posts |
| `post.php` | Password gate with session-based unlock, CSRF-protected password form |
| `rss.php` | Hide content of password-protected posts |
| `templates/index_template.php` | Show 🔒 on password-protected posts |
| `admin/create-post.php` | Refresh CSRF token from upload response for multi-image uploads |
| `admin/edit-post.php` | Refresh CSRF token from upload response for multi-image uploads |
| `README.md` | Updated with v1.4.0 changelog, proxy config docs |
| `SECURITY.md` | Updated version table, security architecture docs |
| `data/version.json` | Version → 1.4.0 |
| `data/settings/version.json` | Version → 1.4.0 |
| `update/manifest.json` | v1.4.0 update manifest with SHA-256 checksums |
| `update/files/*` | All modified files with correct version |