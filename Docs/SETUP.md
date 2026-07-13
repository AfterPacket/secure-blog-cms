# 🚀 Quick Setup Guide - Secure Blog CMS

Get your secure blog up and running in 5 minutes!

## ⚡ Quick Start (3 Steps)

### 1️⃣ Upload Files
Upload all files to your web server directory:
- Via FTP/SFTP to `/public_html/` or `/var/www/html/`
- Or place in local server folder (XAMPP, WAMP, etc.)

### 2️⃣ Change Admin Password
**CRITICAL SECURITY STEP!**

Open a terminal/command prompt and run:
```bash
php -r "echo password_hash('YourNewSecurePassword123!', PASSWORD_ARGON2ID);"
```

Or create a temporary file `hash.php`:
```php
<?php
echo password_hash('YourNewSecurePassword123!', PASSWORD_ARGON2ID);
```

Copy the output hash, then edit `config.php`:
```php
define('ADMIN_PASSWORD_HASH', 'paste_your_hash_here');
```

**Delete `hash.php` after use!**

### 3️⃣ Access Your Site
- **Blog**: `http://yourdomain.com/index.php`
- **Admin**: `http://yourdomain.com/login.php`
- **Username**: `admin`
- **Password**: (Your new password from Step 2)

---

## 🔧 Detailed Setup

### For Linux/Unix Servers

```bash
# Navigate to web directory
cd /var/www/html/blog/

# Set correct permissions
chmod 700 data/
chmod 700 data/posts/
chmod 700 data/sessions/
chmod 700 data/logs/
chmod 700 data/backups/

# Set ownership (replace www-data with your web server user)
chown -R www-data:www-data data/

# Verify .htaccess is present
ls -la .htaccess
```

### For Windows (XAMPP/WAMP)

1. Place files in `C:\xampp\htdocs\blog\`
2. Right-click `data` folder → Properties → Security
3. Give full control to the web server user
4. Access: `http://localhost/blog/`

### For Shared Hosting

1. Upload via cPanel File Manager or FTP
2. Use cPanel File Manager to set permissions (700 for data directories)
3. Edit `config.php` via cPanel editor
4. Access via your domain

---

## ⚙️ Configuration (Optional)

Edit `config.php` to customize:

```php
// Site Information
define('SITE_NAME', 'My Awesome Blog');
define('SITE_DESCRIPTION', 'Thoughts and ideas');
define('SITE_URL', 'https://yourdomain.com');

// Admin Username (if you want to change it)
define('ADMIN_USERNAME', 'admin'); // Change to your preferred username

// Session Timeout (in seconds)
define('SESSION_LIFETIME', 3600); // 1 hour

// Posts Per Page
define('POSTS_PER_PAGE', 10);
```

---

## 🔐 Security Checklist

- [ ] Changed default admin password
- [ ] Updated `SITE_URL` in config.php
- [ ] Verified `data/` directory is NOT web-accessible
- [ ] `.htaccess` file is present and active (Apache)
- [ ] PHP version is 7.4 or higher
- [ ] Tested login functionality
- [ ] Created a test post
- [ ] Verified security logs are being created

---

## 🎯 First Steps After Setup

### 1. Login
Navigate to `http://yourdomain.com/login.php`

### 2. Create Your First Post
1. Click "Create New Post" in admin dashboard
2. Enter title and content
3. Select "Published" status
4. Click "Create Post"

### 3. View Your Blog
Visit `http://yourdomain.com/index.php` to see your post

### 4. Create a Backup
1. Go to admin dashboard
2. Scroll to "Backup & Restore"
3. Click "Create Backup"

---

## 🚨 Troubleshooting

### Can't Login?
- **Default credentials**: username `admin`, password `ChangeThisSecurePassword123!`
- **Changed password?** Make sure you updated the hash in `config.php`
- **Account locked?** Wait 15 minutes or delete `data/sessions/login_*.json`

### "Permission Denied" Errors?
```bash
# Linux/Unix
chmod -R 700 data/
chown -R www-data:www-data data/

# Check if web server user is correct
ps aux | grep apache  # or nginx
```

### Data Directory Accessible?
- Check `.htaccess` in data folder
- Verify Apache mod_rewrite is enabled
- For Nginx, add deny rules in config

### Posts Not Saving?
- Check `data/posts/` has write permissions (700)
- Check PHP error log
- Verify PHP `memory_limit` is at least 128M

### Session Issues?
- Clear browser cookies
- Check `data/sessions/` permissions
- Verify PHP session settings in `php.ini`

---

## 📱 Testing

### Test Security Features

1. **CSRF Protection**: Try submitting a form without a token (should fail)
2. **XSS Protection**: Try adding `<script>alert('test')</script>` in a post (should be sanitized)
3. **Rate Limiting**: Try logging in incorrectly 10 times (should be rate limited)
4. **Session Security**: Try using an old session cookie (should fail)

### Test Functionality

- [ ] Create post
- [ ] Edit post
- [ ] Delete post
- [ ] Publish/unpublish post
- [ ] Search functionality
- [ ] Pagination
- [ ] Backup creation
- [ ] Backup restoration

---

## 🌐 Production Deployment

### Before Going Live

1. **Enable HTTPS**: Get SSL certificate (Let's Encrypt is free)
2. **Update .htaccess**: Uncomment HTTPS redirect rules
3. **Update config.php**: Change `SITE_URL` to https://
4. **Set error display**: Ensure `display_errors = 0` in production
5. **Test thoroughly**: Test all features with HTTPS
6. **Create backup**: Before launch, create a backup
7. **Monitor logs**: Check `data/logs/security_*.log` regularly

### Recommended Additions

```apache
# In .htaccess, uncomment:
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# And uncomment:
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

---

## 📞 Getting Help

1. **Read README.md**: Comprehensive documentation
2. **Check logs**: `data/logs/security_*.log` and PHP error log
3. **Verify requirements**: PHP 7.4+, proper permissions
4. **Test basic PHP**: Create `info.php` with `<?php phpinfo(); ?>`

---

## 🎉 You're Done!

Your secure blog CMS is ready! Here's what you have:

✅ SQL-free blog platform  
✅ XSS & CSRF protection  
✅ Secure admin panel  
✅ Automatic backups  
✅ Search functionality  
✅ Responsive design  
✅ Security logging  

**Next**: Start creating content and share your blog with the world! 🚀

---

**Need to reset everything?**
```bash
# Delete all data and start fresh
rm -rf data/posts/*
rm -rf data/sessions/*
rm -rf data/logs/*
rm -rf data/backups/*
```

**Remember**: Security is ongoing. Keep PHP updated and monitor your logs regularly!