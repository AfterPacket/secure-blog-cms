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
- CSRF protection on all forms
- XSS sanitization and output escaping
- CSP and standard HTTP security headers
- Rate limiting and account lockout
- Session hardening and regeneration
- Security event logging to `data/logs/`

## Requirements

- PHP 7.4+ (PHP 8.x recommended)
- Web server (Apache/Nginx) or PHP built-in server
- Write access to the `data/` directory
- Extensions (optional but recommended):
  - `curl` for Pinata IPFS pinning
  - `zip` for export ZIP bundles

## Quick Start

### Option A: Installer (recommended)
1. Copy the project into your web root.
2. Ensure the `data/` directory is writable by the web server.
3. Visit `/install/index.php` in your browser and complete the wizard.
4. After install, keep `data/installed.lock` in place (re-install requires deleting it).

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
- Automatic updates can be enabled for minor/security releases.

### Manual update
1. Backup `data/` and `includes/config.php`.
2. Replace application files with the new release.
3. Re-check your settings and log in to confirm.

## Maintainer: Build a release package

### Windows
Run `tools/build.bat` (requires PHP path configured inside the script).

### Cross-platform
```
php tools/build-update.php <version>
```

This regenerates `update/files/` and `update/manifest.json`.

## Project Layout (high level)

```
secure-blog-cms/
  admin/            Admin UI (posts, comments, users, settings, resilience, upgrade)
  data/             JSON data storage (posts, users, comments, logs, backups)
  includes/         Core classes (Security, Storage, Comments, Resilience, Uploads)
  install/          Installation wizard
  templates/        Public templates
  tools/            Build and release helpers
  update/           Update packages and manifest
  index.php         Public homepage
  post.php          Single post view
  rss.php           RSS feed
```

## Troubleshooting

- Cannot log in: verify `ADMIN_PASSWORD_HASH` or reset via installer; check lockouts.
- Permission errors: ensure `data/` and subfolders are writable by the web server.
- Comments not posting: check hCaptcha configuration and `data/comments/` permissions.
- Image uploads failing: verify `data/uploads/images/` permissions and PHP upload limits.
- Static export fails: confirm `data/exports/` is writable and `zip` extension is enabled.

## Security Disclosure

If you discover a security issue, please avoid public disclosure. Share details privately with the maintainer so a fix can be prepared.

## License

This project is provided as-is for educational and production use.

---

Version: 1.3.2
Last Updated: 2026-01-25
Security Level: High
