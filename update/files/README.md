# 🔒 Secure Blog CMS - SQL-Free Publishing Platform

A highly secure, SQL-free blog content management system built with PHP, featuring comprehensive protection against XSS, CSRF, injection attacks, and other security vulnerabilities.

## 🌟 Features

### Core Functionality
- ✅ **SQL-Free Architecture** - File-based JSON storage, no database required
- 📝 **Post Management** - Create, edit, delete, and publish blog posts
- 🔍 **Search Functionality** - Full-text search across posts
- 📄 **Pagination** - Efficient page navigation
- 📊 **Statistics Dashboard** - Track posts, views, and engagement
- 💾 **Backup & Restore** - Automatic and manual backup system
- 📱 **Responsive Design** - Mobile-friendly interface

### Security Features
- 🔐 **CSRF Protection** - Token-based request validation
- 🛡️ **XSS Prevention** - Multi-layer input sanitization and output escaping
- 🚫 **Injection Attack Prevention** - Comprehensive input validation
- 🔒 **Session Security** - Secure session management with fingerprinting
- ⏱️ **Rate Limiting** - Protection against brute force attacks
- 🔑 **Secure Authentication** - Argon2id password hashing
- 📝 **Security Logging** - Detailed audit trail of all security events
- 🎯 **Content Security Policy** - Strict CSP headers
- 🔐 **HTTP Security Headers** - X-Frame-Options, X-Content-Type-Options, etc.
- 🚷 **Directory Traversal Prevention** - Path sanitization
- 🔄 **Session Regeneration** - Automatic session ID rotation
- 🚪 **Account Lockout** - Brute force protection with temporary lockouts

## 📋 Requirements

- PHP 7.4 or higher (PHP 8.x recommended)
- Web server (Apache, Nginx, or built-in PHP server)
- Write permissions for data directories
- HTTPS (recommended for production)

## 🚀 Installation

### Step 1: Download Files
Place all files in your web server directory (e.g., `/var/www/html/blog/` or `C:\xampp\htdocs\blog\`)

### Step 2: Set Permissions
```bash
# Linux/Unix
chmod 700 data/
chmod 700 data/posts/
chmod 700 data/users/
chmod 700 data/sessions/
chmod 700 data/logs/
chmod 700 data/backups/

# Windows - Use File Explorer to set permissions
# Right-click folders > Properties > Security > Edit
```

### Step 3: Configure Admin Credentials
Edit `config.php` and change the default admin password:

```php
// Generate a new password hash
// Run this in terminal or create a temporary PHP file:
php -r "echo password_hash('YourSecurePassword123!', PASSWORD_ARGON2ID);"

// Then update ADMIN_PASSWORD_HASH in config.php
define('ADMIN_PASSWORD_HASH', 'your_generated_hash_here');
```

### Step 4: Update Site Settings
Edit `config.php` to customize your site:

```php
define('SITE_NAME', 'Your Blog Name');
define('SITE_DESCRIPTION', 'Your blog description');
define('SITE_URL', 'https://yourdomain.com');
```

### Step 5: Configure HTTPS (Production Only)
For HTTPS sites, update these settings in `config.php`:

```php
ini_set('session.cookie_secure', '1'); // Already set, just verify
```

### Step 6: Access Your Site
- **Public Blog**: `http://yourdomain.com/index.php`
- **Admin Panel**: `http://yourdomain.com/admin.php`
- **Login**: `http://yourdomain.com/login.php`

**Default Credentials:**
- Username: `admin`
- Password: `ChangeThisSecurePassword123!` (CHANGE THIS IMMEDIATELY!)

## 🎯 Usage Guide

### Creating a New Post
1. Log in to admin panel (`admin.php`)
2. Click "Create New Post"
3. Fill in the title and content
4. Choose status: Draft or Published
5. Click "Create Post"

### Editing Posts
1. Go to admin dashboard
2. Find the post in the list
3. Click "Edit" button
4. Make changes and save

### Deleting Posts
1. Go to admin dashboard
2. Click "Delete" button next to the post
3. Confirm deletion

### Creating Backups
1. Go to admin dashboard
2. Scroll to "Backup & Restore" section
3. Click "Create Backup"
4. Backups are stored in `data/backups/`

### Restoring from Backup
1. Go to admin dashboard
2. Find the backup in the list
3. Click "Restore" button
4. Confirm restoration (this will replace current data)

## 🔐 Security Best Practices

### Essential Steps
1. **Change Default Password** - Update admin credentials immediately after installation
2. **Use HTTPS** - Always use SSL/TLS certificates in production
3. **Strong Passwords** - Use passwords with 12+ characters, mixed case, numbers, and symbols
4. **Regular Backups** - Enable automatic backups or create manual backups regularly
5. **Update PHP** - Keep PHP version updated to latest stable release
6. **Restrict File Permissions** - Ensure data directories are not web-accessible
7. **Monitor Logs** - Regularly check `data/logs/security_*.log` for suspicious activity
8. **Disable Error Display** - Set `display_errors = 0` in production

### .htaccess Protection (Apache)
Create `.htaccess` in the root directory:

```apache
# Prevent access to sensitive files
<FilesMatch "^(config\.php|\.git|\.env)">
    Order allow,deny
    Deny from all
</FilesMatch>

# Force HTTPS (if SSL is enabled)
# RewriteEngine On
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Nginx Configuration
Add to your Nginx configuration:

```nginx
# Deny access to data directory
location ~ ^/data/ {
    deny all;
    return 403;
}

# Deny access to config
location ~ ^/config\.php {
    deny all;
    return 403;
}
```

## 📁 Directory Structure

```
secure-blog-cms/
├── config.php              # Configuration file
├── index.php               # Public blog homepage
├── post.php                # Single post view
├── admin.php               # Admin dashboard
├── login.php               # Login page
├── logout.php              # Logout handler
├── create-post.php         # Create new post
├── edit-post.php           # Edit existing post
├── includes/
│   ├── Security.php        # Security class (XSS, CSRF, etc.)
│   └── Storage.php         # File-based storage class
├── data/                   # Data directory (NOT web-accessible)
│   ├── posts/              # Blog posts (JSON files)
│   ├── users/              # User data
│   ├── sessions/           # Session data
│   ├── logs/               # Security logs
│   ├── backups/            # Backup files
│   └── .htaccess           # Deny access
└── README.md               # This file
```

## 🛡️ Security Features Explained

### XSS Protection
- **Input Sanitization**: All user input is sanitized using multiple filters
- **Output Escaping**: All output is escaped using `htmlspecialchars()` with proper flags
- **Content Security Policy**: Strict CSP headers prevent inline scripts
- **Attribute Filtering**: Dangerous HTML attributes are removed

### CSRF Protection
- **Token Generation**: Unique tokens for each form and session
- **Token Validation**: Constant-time comparison prevents timing attacks
- **One-Time Tokens**: Tokens are invalidated after use
- **Token Expiration**: Tokens expire after 1 hour

### Session Security
- **Secure Cookies**: HttpOnly, Secure, and SameSite flags enabled
- **Session Fingerprinting**: Detects session hijacking attempts
- **Session Regeneration**: Periodic session ID rotation
- **Session Timeout**: Automatic logout after inactivity

### Brute Force Protection
- **Rate Limiting**: Maximum 10 login attempts per 10 minutes
- **Account Lockout**: Temporary 15-minute lockout after 5 failed attempts
- **Login Attempt Tracking**: Failed attempts are logged
- **IP-Based Throttling**: Rate limits per IP address

### Input Validation
- **Type-Specific Sanitization**: Different sanitization for different data types
- **Length Validation**: Maximum lengths enforced
- **Pattern Validation**: Regex patterns for usernames, slugs, etc.
- **Null Byte Removal**: Protection against null byte injection
- **Directory Traversal Prevention**: Path sanitization

## 📊 Configuration Options

### File Limits
```php
define('MAX_POST_TITLE_LENGTH', 200);      // Maximum title length
define('MAX_POST_CONTENT_LENGTH', 50000);   // Maximum content length
define('MAX_POST_EXCERPT_LENGTH', 500);     // Maximum excerpt length
```

### Session Settings
```php
define('SESSION_LIFETIME', 3600);           // 1 hour
define('CSRF_TOKEN_LIFETIME', 3600);        // 1 hour
```

### Authentication
```php
define('MAX_LOGIN_ATTEMPTS', 5);            // Before lockout
define('LOGIN_LOCKOUT_TIME', 900);          // 15 minutes
define('PASSWORD_MIN_LENGTH', 12);          // Minimum password length
```

### Backup
```php
define('AUTO_BACKUP', true);                // Enable auto-backup
define('MAX_BACKUPS', 10);                  // Maximum backup files to keep
```

## 🐛 Troubleshooting

### Cannot Login
- **Check credentials**: Default username is `admin`
- **Check lockout**: Wait 15 minutes if account is locked
- **Check logs**: View `data/logs/security_*.log`
- **Reset password**: Generate new hash and update `config.php`

### Permission Errors
```bash
# Fix directory permissions
chmod -R 700 data/
chown -R www-data:www-data data/  # Linux
```

### Posts Not Saving
- Check write permissions on `data/posts/` directory
- Check PHP error logs
- Verify PHP has enough memory (`memory_limit` in php.ini)

### Session Issues
- Clear browser cookies
- Check `data/sessions/` permissions
- Verify `session.save_path` in PHP configuration

## 📝 Logging

All security events are logged to `data/logs/security_YYYY-MM-DD.log`

### Log Format
```
[2025-01-14 12:34:56] IP: 192.168.1.100 | Event: Successful login | Details: admin | User-Agent: Mozilla/5.0...
```

### Events Logged
- Login attempts (successful and failed)
- Account lockouts
- CSRF validation failures
- Post creation/modification/deletion
- Backup operations
- Rate limit violations
- Session anomalies

## 🔄 Updating

1. **Backup your data**: Download `data/` folder
2. **Replace files**: Update all PHP files except `config.php`
3. **Check config**: Compare new `config.php` for new settings
4. **Test**: Verify functionality in a test environment first

## 🤝 Contributing

This is a standalone secure CMS. To customize:
1. Modify `config.php` for settings
2. Edit `includes/Security.php` for security enhancements
3. Update `includes/Storage.php` for storage modifications
4. Customize CSS in individual PHP files

## 📄 License

This project is provided as-is for educational and production use.

## ⚠️ Important Notes

- **SQL-Free**: This system does NOT use SQL databases
- **File-Based Storage**: All data is stored in JSON files
- **Single Admin**: Currently supports one admin user
- **Production Ready**: Designed with security-first approach
- **No Dependencies**: Pure PHP, no external libraries required

## 🐛 Troubleshooting

### Cannot Login
- **Check credentials**: Default username is `admin`
- **Check lockout**: Wait 15 minutes if account is locked
- **Check logs**: View `data/logs/security_*.log`
- **Reset password**: Generate new hash and update `config.php`

### Permission Errors
```bash
# Fix directory permissions
chmod -R 700 data/
chown -R www-data:www-data data/  # Linux
```

### Posts Not Saving
- Check write permissions on `data/posts/` directory
- Check PHP error logs
- Verify PHP has enough memory (`memory_limit` in php.ini)

### Image Upload Issues
- Image uploads need to be fixed and implemented properly
- Current implementation requires security and functionality improvements

### Session Issues
- Clear browser cookies
- Check `data/sessions/` permissions
- Verify `session.save_path` in PHP configuration
## 🆘 Support

For issues or questions:
1. Check this README
2. Review security logs in `data/logs/`
3. Verify file permissions
4. Check PHP error logs
5. Ensure PHP version meets requirements

## 🔐 Security Disclosure

If you discover a security vulnerability, please:
1. Do NOT open a public issue
2. Document the vulnerability
3. Contact the administrator privately
4. Allow time for a fix before public disclosure

## 📚 Additional Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)

---

**Version**: 1.2.2  
**Last Updated**: 2026-01-20  
**Security Level**: High 🔒

**Remember**: Security is a continuous process. Regularly update, monitor, and audit your system.
