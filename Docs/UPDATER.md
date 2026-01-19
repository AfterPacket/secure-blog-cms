
---

## 5) Add docs for updater + manifest format

Create `docs/UPDATER.md`:

```markdown
# Safe Delta Auto-Updater

## How it works
- Fetches `update/manifest.json` over HTTPS
- Downloads only changed files (SHA-256 verified)
- Backs up replaced files to `data/backups/`
- NEVER touches `/data` or `includes/config.php`

## Update folder layout (in the repo)
update/
  manifest.json
  files/
    (mirrors project file paths)

## Manifest format
```json
{
  "version": "1.0.2",
  "released": "2026-01-19",
  "base": "https://raw.githubusercontent.com/AfterPacket/secure-blog-cms/main/update/files",
  "files": {
    "post.php": { "sha256": "...", "size": 1234 }
  }
}
