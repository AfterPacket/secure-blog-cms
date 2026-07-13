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