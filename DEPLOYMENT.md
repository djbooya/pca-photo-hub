# PCA Photo Hub - Deployment Guide

Quick guide to deploy PCA Photo Hub to production.

## Pre-Deployment Checklist

- [ ] Google APIs configured (see GOOGLE_SETUP.md)
- [ ] `.env` file created with all required variables
- [ ] Service account JSON file secured outside web root
- [ ] Local testing completed successfully
- [ ] Albums and folders created in Google Drive
- [ ] Google Sheets configured with album data

## Hosting Requirements

- **PHP Version**: 7.4 or higher
- **Extensions**: cURL (for Google APIs)
- **Web Server**: Apache (with mod_rewrite) or Nginx
- **Disk Space**: 100MB minimum (for caching and logs)
- **SSL/TLS**: Recommended for production (set `SESSION_COOKIE_SECURE=true`)

## Deployment Steps

### 1. Upload Files to Server

Option A - Git (recommended):
```bash
git clone <your-repo-url> /var/www/pca-photo-hub
cd /var/www/pca-photo-hub
composer install --no-dev
```

Option B - FTP/SFTP:
- Upload all files to `/public_html/pca-photo-hub/`
- Upload composer dependencies (run `composer install` locally, upload `vendor/` folder)

### 2. Set File Permissions

```bash
cd /var/www/pca-photo-hub

# Make storage directories writable
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/uploads/
chmod 755 storage/logs/

# Public directory should be readable
chmod 755 public/
chmod 644 public/*
chmod 644 public/css/*
chmod 644 public/js/*
```

### 3. Configure .env File

1. Copy `.env.example` to `.env`
2. Edit `.env` with production values:
   ```bash
   nano .env
   ```
3. Update these values:
   - `GOOGLE_SERVICE_ACCOUNT_JSON` - Absolute path to JSON file
   - `GOOGLE_DRIVE_ROOT_FOLDER_ID` - Your root folder ID
   - `GOOGLE_SHEETS_CONFIG_ID` - Your sheets ID
   - `APP_DEBUG` - Set to `false`
   - `BASE_URL` - Your production domain (https://yourdomain.com)
   - `SESSION_COOKIE_SECURE` - Set to `true` if using HTTPS

### 4. Configure Web Server

#### Apache

Set document root to `public/` directory:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAdmin admin@yourdomain.com
    
    DocumentRoot /var/www/pca-photo-hub/public
    
    <Directory /var/www/pca-photo-hub/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    ErrorLog ${APACHE_LOG_DIR}/pca-photo-hub-error.log
    CustomLog ${APACHE_LOG_DIR}/pca-photo-hub-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAdmin admin@yourdomain.com
    
    DocumentRoot /var/www/pca-photo-hub/public
    
    <Directory /var/www/pca-photo-hub/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/key.key
    SSLCertificateChainFile /path/to/your/chain.crt
    
    ErrorLog ${APACHE_LOG_DIR}/pca-photo-hub-error.log
    CustomLog ${APACHE_LOG_DIR}/pca-photo-hub-access.log combined
</VirtualHost>
```

#### Nginx

```nginx
upstream php_backend {
    server unix:/var/run/php-fpm.sock;
}

server {
    listen 80;
    server_name yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    root /var/www/pca-photo-hub/public;
    index index.php;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/key.key;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Disable direct access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(config|storage|vendor)/ {
        deny all;
    }
    
    # PHP routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 5. Install SSL Certificate

Use Let's Encrypt for free SSL:

```bash
# Install Certbot
apt-get install certbot python3-certbot-apache

# Generate certificate (for Apache)
certbot certonly --apache -d yourdomain.com

# Or for Nginx
certbot certonly --nginx -d yourdomain.com
```

### 6. Test Installation

```bash
# Check if everything is working
curl https://yourdomain.com/

# Check logs for errors
tail -f storage/logs/php.log
tail -f /var/log/apache2/pca-photo-hub-error.log  # Apache
tail -f /var/log/nginx/error.log  # Nginx
```

### 7. Set Up Automated Cache Clearing (Optional)

Add cron job to clear cache every hour:

```bash
crontab -e

# Add this line:
0 * * * * rm -f /var/www/pca-photo-hub/storage/cache/*.json
```

---

## Post-Deployment

### 1. Test Core Functionality

- [ ] Visit homepage, all albums display
- [ ] Click an album, password prompt appears
- [ ] Enter correct password, gallery loads
- [ ] Upload a test photo
- [ ] Delete the test photo
- [ ] View album in Google Drive (file should be there)
- [ ] Test on mobile device

### 2. Monitor

Keep an eye on:
- Error logs: `storage/logs/php.log`
- API rate limits (Google Drive/Sheets)
- Storage space usage
- Uptime and performance

### 3. Backup

Regularly backup:
- `.env` file (configuration)
- `storage/uploads/metadata.json` (upload tracking)
- Google Drive (user photos are there)

## Troubleshooting Deployment Issues

### "403 Forbidden"
- Check file permissions on `public/` directory
- Verify web server user can read files

### "Failed to connect to Google"
- Verify service account JSON path in `.env`
- Check Google APIs are enabled
- Verify IP isn't blocked by Google

### "Sessions not persisting"
- Check cookies are enabled
- Verify `SESSION_COOKIE_SECURE` matches protocol (http/https)
- Check browser privacy settings

### "Cache not working"
- Verify `storage/cache/` is writable
- Check PHP can write to directory
- Manually clear cache: `rm storage/cache/*.json`

### "Uploads failing"
- Check `max_upload_size` in php.ini (should be > 25MB)
- Verify `storage/uploads/` directory is writable
- Check Google Drive API quota

```bash
# php.ini settings to verify
grep "upload_max_filesize" /etc/php/*/apache2/php.ini
grep "post_max_size" /etc/php/*/apache2/php.ini

# Should show at least 25M
upload_max_filesize = 100M
post_max_size = 100M
```

---

## Updating the Application

To update to a new version:

```bash
cd /var/www/pca-photo-hub

# Pull latest code
git pull

# Update dependencies
composer install --no-dev

# Clear cache
rm storage/cache/*.json

# Restart PHP-FPM (if applicable)
systemctl restart php-fpm
```

---

## Support & Maintenance

- **Logs**: Check `storage/logs/php.log` for errors
- **Google API Issues**: Check Google Cloud Console for quota/API status
- **Performance**: Monitor with analytics tools
- **Backups**: Automate with cron jobs or backup software

---

## Security Checklist

- [ ] HTTPS enabled
- [ ] Service account JSON is not in web root
- [ ] `.env` file is not version controlled
- [ ] File permissions are restrictive (755 for dirs, 644 for files)
- [ ] `.htaccess` or Nginx rules prevent access to sensitive files
- [ ] Regular backups are automated
- [ ] Error logs don't expose system information
- [ ] Session cookies have secure flag set
- [ ] CSRF protection in place (consider adding CSRF tokens for future)

---

## Next Steps

1. **Test Thoroughly**: Verify all features work in production
2. **Monitor Logs**: Watch for errors the first week
3. **Gather Feedback**: Ask users about their experience
4. **Plan Updates**: Document any feature requests
5. **Automate Backups**: Set up daily backup routine
