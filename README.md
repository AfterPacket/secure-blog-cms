# Secure Blog CMS

A lightweight PHP blog CMS with:
- Admin panel (posts, categories, users)
- Comment moderation queue
- Private + password-protected posts
- Optional hCaptcha for comments
- Safe delta auto-updater (pulls only changed files, never touches `/data`)

## Requirements
- PHP 7.4+ (8.x supported)
- Extensions: `curl`, `json`, `openssl`
- Apache or Nginx
- Writable `data/` directory by the web server

## Install
1. Upload the files to a folder, e.g. `/secure-blog-cms/`
2. Ensure permissions:
   - `data/` must be writable by PHP
3. Visit:
   - `/secure-blog-cms/install/` (if you have installer)
   - or go directly to `/secure-blog-cms/admin/login.php`

## Security / Secrets
Do **not** hardcode secrets in the repo.

### hCaptcha
Set:
- `HCAPTCHA_SECRET` (server env var)
- `HCAPTCHA_SITEKEY` (can be in settings or env)

Apache example:
```apache
SetEnv HCAPTCHA_SECRET "your-secret"
SetEnv HCAPTCHA_SITEKEY "your-sitekey"
