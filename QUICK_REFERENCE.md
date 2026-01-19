# 🔒 Secure Blog CMS - Quick Reference Card

## 🚀 Installation (5 Minutes)

1. Upload all files to web server
2. Navigate to `http://yourdomain.com/install.php`
3. Follow 3-step wizard
4. Login at `http://yourdomain.com/login.php`
5. Install file auto-deletes on first login

**Default Credentials:**
- Username: `admin`
- Password: `ChangeThisSecurePassword123!`

⚠️ **CHANGE PASSWORD IMMEDIATELY!**

---

## 📁 Essential Files

| File | Purpose |
|------|---------|
| `config.php` | All configuration settings |
| `includes/Security.php` | Security functions (XSS, CSRF, etc.) |
| `includes/Storage.php` | Data management |
| `admin.php` | Main admin dashboard |
| `settings.php` | Site configuration |
| `.htaccess` | Web server security |

---

## 🎯 Common Tasks

### Create New Post
1. Login → Dashboard
2. Click "Create New Post"
3. Fill title & content
4. Choose status (Draft/Published)
5. Click "Create Post"

### Edit Post
1. Dashboard → Find post
2. Click "Edit"
3. Modify content
4. Click "Update Post"

### Create Backup
1. Dashboard → Scroll to "Backup & Restore"
2. Click "Create Backup"
3. Backup saved to `data/backups/`

### Change Site Settings
1. Dashboard → Click "Settings"
2. Modify site name, description, etc.
3. Click "Save Settings"

### Change Admin Password
1. Generate new hash:
   ```bash
   php -r "echo password_hash('NewPassword123!', PASSWORD_ARGON2ID);"
   ```
2. Edit `config.php`
3. Replace `ADMIN_PASSWORD_HASH` value

---

## 🔐 Security Features

✅ **XSS Protection** - Multi-layer input sanitization  
✅ **CSRF Protection** - Token-based validation  
✅ **Session Security** - Fingerprinting & regeneration  
✅ **Brute Force Protection** - 5 attempts → 15-min lockout  
✅ **Rate Limiting** - Max 10 login attempts/10 min  
✅ **Security Logging** - All events logged to `data/logs/`  
✅ **SQL-Free** - Zero SQL injection risk  
✅ **Secure Headers** - CSP, X-Frame-Options, etc.  

---

## 📂 Directory Structure

```
secure-blog-cms/
├── install.php              (deletes after first login)
├── config.php               (main configuration)
├── admin.php                (admin dashboard)
├── settings.php             (site settings)
├── index.php                (public blog)
├── post.php                 (single post view)
├── create-post.php          (create post)
├── edit-post.php            (edit post)
├── login.php                (authentication)
├── logout.php               (logout handler)
├── includes/
│   ├── Security.php         (security class)
│   └── Storage.php          (storage class)
└── data/                    (NOT web-accessible)
    ├── posts/               (blog posts - JSON)
    ├── sessions/            (session data)
    ├── logs/                (security logs)
    ├── backups/             (automatic backups)
    ├── settings/            (site settings)
    └── uploads/             (ready for images)
```

---

## ⚙️ Configuration Options

**File:** `config.php`

```php
// Admin Credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', 'your_hash_here');

// Site Information
define('SITE_NAME', 'Your Blog');
define('SITE_DESCRIPTION', 'Your description');
define('SITE_URL', 'https://yourdomain.com');

// Security Settings
define('SESSION_LIFETIME', 3600);        // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);       // 15 minutes
define('PASSWORD_MIN_LENGTH', 12);

// Content Limits
define('MAX_POST_TITLE_LENGTH', 200);
define('MAX_POST_CONTENT_LENGTH', 50000);
define('POSTS_PER_PAGE', 10);

// Backup
define('AUTO_BACKUP', true);
define('MAX_BACKUPS', 10);
```

---

## 🐛 Troubleshooting

### Can't Login
- **Check credentials** (default: admin / ChangeThisSecurePassword123!)
- **Wait 15 minutes** if account locked
- **Check logs**: `data/logs/security_YYYY-MM-DD.log`
- **Reset password** in `config.php`

### Permission Errors
```bash
chmod -R 700 data/
chown -R www-data:www-data data/
```

### Posts Not Saving
- Check `data/posts/` is writable (chmod 700)
- Check PHP error log
- Verify disk space

### Session Issues
- Clear browser cookies
- Check `data/sessions/` permissions
- Restart browser

### 500 Internal Server Error
- Check `.htaccess` syntax
- Check PHP error log
- Verify PHP version >= 7.4

---

## 📊 Key Locations

| Item | Location |
|------|----------|
| Security Logs | `data/logs/security_*.log` |
| PHP Errors | Check server error log |
| Backups | `data/backups/backup_*.json` |
| Post Data | `data/posts/*.json` |
| Settings | `data/settings/site.json` |
| Sessions | `data/sessions/` |

---

## 🔒 Security Checklist

**Before Production:**
- [ ] Changed default password
- [ ] Enabled HTTPS
- [ ] Updated SITE_URL in config.php
- [ ] Set `display_errors = 0`
- [ ] Verified data/ not web-accessible
- [ ] Tested backup/restore
- [ ] Reviewed security logs
- [ ] Set proper file permissions
- [ ] Enabled HSTS header (if using HTTPS)

---

## 🌐 URLs

| Page | URL |
|------|-----|
| Blog Home | `/index.php` |
| Admin Login | `/login.php` |
| Dashboard | `/admin.php` |
| Settings | `/settings.php` |
| Create Post | `/create-post.php` |
| View Post | `/post.php?slug=post-slug` |

---

## 📝 Post Statuses

- **Draft** - Not visible to public, editable
- **Published** - Visible to everyone

---

## 🔧 Maintenance

### Daily
- Check security logs for anomalies

### Weekly
- Review failed login attempts
- Create manual backup

### Monthly
- Update PHP version
- Review disk usage
- Clean old logs (keep last 30 days)

---

## 📞 Emergency Procedures

### Locked Out
1. Delete: `data/sessions/login_*.json`
2. Wait 15 minutes
3. Try login again

### Lost Password
1. Generate new hash (see "Change Admin Password")
2. Edit `config.php`
3. Replace hash value

### Data Corruption
1. Go to Dashboard → Backup & Restore
2. Select recent backup
3. Click "Restore"

### Security Breach
1. Check logs: `data/logs/security_*.log`
2. Change admin password immediately
3. Review recent posts for modifications
4. Check for suspicious uploads (when implemented)
5. Restore from clean backup if needed

---

## 💡 Quick Tips

- **Backup before updates** - Always create backup before changes
- **Monitor logs regularly** - Check `data/logs/` weekly
- **Use strong passwords** - Minimum 12 characters, mixed types
- **Enable HTTPS** - Required for production
- **Test in staging** - Never test features in production
- **Read logs** - They tell you what's happening
- **Keep PHP updated** - Security patches are important

---

## 🎯 Performance

| Metric | Target |
|--------|--------|
| Page Load | < 100ms |
| Memory Usage | < 32MB per request |
| File Storage | ~10KB per post |
| Max Posts | Limited by disk space |

---

## 📚 Documentation

- **Full Docs**: `README.md`
- **Setup Guide**: `SETUP.md`
- **Features**: `FEATURES.md`
- **Next Steps**: `NEXT_STEPS.md`
- **Project Summary**: `PROJECT_SUMMARY.md`

---

## 🚨 Security Incidents

**If you detect:**
- Unusual login patterns
- Failed upload attempts
- CSRF token violations
- Rate limit triggers
- Account lockouts

**Action:**
1. Check `data/logs/security_*.log`
2. Review recent changes
3. Change admin password
4. Create backup of current state
5. Consider restoring from clean backup

---

## ✅ Health Check

**Weekly verification:**
```bash
# Check permissions
ls -la data/

# Check logs
tail -f data/logs/security_*.log

# Check disk space
df -h

# Check PHP version
php -v

# Verify backups exist
ls -la data/backups/
```

---

## 🎓 Remember

1. **Security is continuous** - Not one-time setup
2. **Logs are your friend** - Read them regularly
3. **Backup frequently** - Automated + manual
4. **Test changes** - Staging environment first
5. **Monitor activity** - Watch for anomalies
6. **Stay updated** - Keep PHP current
7. **Document changes** - Track what you modify

---

**Version:** 1.0.0  
**Last Updated:** January 14, 2025  
**Security Level:** 🔒 Enterprise Grade  

**Need Help?** Check README.md for comprehensive documentation.

🔒 **Stay Secure!** 🔒