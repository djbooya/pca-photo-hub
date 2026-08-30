# PCA Photo Hub

A file-sharing platform for the Diablo Region of Porsche Club of America to collect event photos from participants. Photos are stored in Google Drive and can later be pushed to Facebook via automation.

## Features

- 🔐 Password-protected photo albums
- 📱 Mobile-friendly responsive design
- 🚀 Easy multi-file uploads
- 🗑️ Time-limited file deletion for uploaders
- 🌐 No credentials needed for end users
- 📊 Album configuration via Google Sheets
- 🎨 Branded with Diablo PCA theme

## Technical Stack

- **Backend**: PHP 7.4+
- **APIs**: Google Drive & Google Sheets
- **Frontend**: HTML5, CSS3, Vanilla JavaScript, Bootstrap 5
- **Storage**: Google Drive
- **Configuration**: Google Sheets + .env file

## Setup Instructions

### 1. Prerequisites

- PHP 7.4 or higher with cURL support
- Composer (PHP dependency manager)
- Google Cloud Project with APIs enabled
- Google Drive and Google Sheets access

### 2. Install Dependencies

```bash
cd pca-photo-hub
composer install
```

This will install:
- `google/apiclient` - Google API PHP client
- `google/auth` - Google authentication library
- `symfony/dotenv` - Environment variable loader

### 3. Google Cloud Setup

#### Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a Project" → "New Project"
3. Enter name: "PCA Photo Hub"
4. Click "Create"

#### Step 2: Enable Required APIs

1. In the left sidebar, go to "APIs & Services" → "Library"
2. Search for and enable:
   - **Google Drive API**
   - **Google Sheets API**

#### Step 3: Create Service Account

1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "Service Account"
3. Enter:
   - Service account name: `pca-photo-hub`
   - Service account ID: (auto-filled)
   - Click "Create and Continue"
4. Grant Editor role (for Drive access)
5. Click "Continue" and "Done"

#### Step 4: Create & Download JSON Key

1. Click on the service account you just created
2. Go to "Keys" tab → "Add Key" → "Create new key"
3. Choose "JSON" and click "Create"
4. Save the downloaded JSON file securely

### 4. Google Drive Setup

1. Create a root folder in Google Drive for all albums (e.g., "PCA Photo Hub")
2. Share this folder with the service account email (`your-service-account@your-project.iam.gserviceaccount.com`)
   - Right-click folder → "Share" → Paste service account email → "Editor"
3. Copy the folder ID from the URL: `https://drive.google.com/drive/folders/{FOLDER_ID}`

### 5. Google Sheets Setup

1. Create a new Google Sheet named "PCA Photo Hub Config"
2. Add these column headers in the first row:
   ```
   Album Name | Password | Upload Start | Upload End | Notes
   ```
3. Add your albums as rows:
   ```
   Spring Autocross 2026 | porsche123 | 2026-03-01 | 2026-04-15 | Mog Park location
   Summer BBQ Photos | bbqpics2026 | 2026-06-01 | 2026-08-31 | Main event
   ```
4. Share the sheet with the service account (Editor access)
5. Copy the Sheet ID from the URL: `https://docs.google.com/spreadsheets/d/{SHEET_ID}/edit`

### 6. Environment Configuration

1. Copy the `.env.example` file to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` and fill in your values:
   ```
   GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/service-account.json
   GOOGLE_DRIVE_ROOT_FOLDER_ID=your_root_folder_id
   GOOGLE_SHEETS_CONFIG_ID=your_sheets_id
   MAX_FILE_SIZE_MB=25
   ALLOWED_MIME_TYPES=image/jpeg,image/png,image/webp
   UPLOAD_TIMEOUT_DAYS=7
   SESSION_COOKIE_EXPIRY=180
   APP_DEBUG=false
   BASE_URL=https://yoursite.com
   TIMEZONE=America/Los_Angeles
   ```

3. Move service account JSON to a secure location (e.g., `/config/service-account.json`)

### 7. Directory Permissions

Ensure these directories are writable by the web server:

```bash
chmod 755 storage/
chmod 755 storage/cache/
chmod 755 storage/uploads/
chmod 755 storage/logs/
```

### 8. Web Server Configuration

#### For Apache

Create `.htaccess` in the `public` directory (already included):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

Set document root to `public` directory in your Apache configuration.

#### For Nginx

```nginx
server {
    listen 80;
    server_name yoursite.com;
    root /path/to/pca-photo-hub/public;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 9. Local Testing

Start a local PHP server for testing:

```bash
cd pca-photo-hub/public
php -S localhost:8000
```

Then visit: `http://localhost:8000`

## File Structure

```
pca-photo-hub/
├── config/
│   ├── config.php              # Configuration loader
│   └── .env.example            # Environment template
├── src/
│   ├── GoogleDriveManager.php   # Google Drive API
│   ├── GoogleSheetsManager.php  # Google Sheets API
│   ├── AlbumManager.php         # Album logic
│   ├── FileUploadHandler.php    # Upload validation
│   └── SessionManager.php       # User session tracking
├── public/
│   ├── index.php               # Homepage
│   ├── album.php               # Album detail page
│   ├── upload.php              # Upload handler
│   ├── delete.php              # Delete handler
│   ├── init.php                # Bootstrap file
│   ├── css/
│   │   └── style.css           # Responsive styles
│   ├── js/
│   │   ├── upload.js           # Upload logic
│   │   └── gallery.js          # Gallery display
│   └── assets/
│       └── diablo-pca-logo.png
├── storage/
│   ├── cache/                  # Cached configuration
│   ├── uploads/                # Upload metadata
│   └── logs/                   # Application logs
├── vendor/                     # Composer dependencies
├── composer.json               # PHP dependencies
└── README.md                   # This file
```

## API Endpoints

### Public Pages

- `GET /` - Homepage with album list
- `GET /album.php?id={folder_id}` - Album detail + upload page

### AJAX Endpoints

- `POST /upload.php` - Handle file uploads
- `POST /delete.php` - Delete user's uploaded file
- `GET /album.php?id={folder_id}&json=1` - Get album data (JSON)

## Configuration

All configuration is managed via `.env` file and Google Sheets.

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `GOOGLE_SERVICE_ACCOUNT_JSON` | Path to service account JSON | Required |
| `GOOGLE_DRIVE_ROOT_FOLDER_ID` | Root folder ID in Drive | Required |
| `GOOGLE_SHEETS_CONFIG_ID` | Sheets ID for album config | Required |
| `MAX_FILE_SIZE_MB` | Max file size in MB | 25 |
| `ALLOWED_MIME_TYPES` | Comma-separated MIME types | image/jpeg,image/png,image/webp |
| `UPLOAD_TIMEOUT_DAYS` | Days users can delete uploads | 7 |
| `SESSION_COOKIE_EXPIRY` | Cookie expiry in days | 180 |
| `APP_DEBUG` | Enable debug mode | false |
| `BASE_URL` | Application base URL | http://localhost:8000 |
| `TIMEZONE` | Application timezone | America/Los_Angeles |

### Google Sheets Configuration

Album configuration is stored in a Google Sheet with these columns:

| Column | Description | Example |
|--------|-------------|---------|
| Album Name | Name of the album (must match Drive folder) | Spring Autocross 2026 |
| Password | Password for uploading (empty = no password) | porsche123 |
| Upload Start | Date when uploads begin (YYYY-MM-DD) | 2026-03-01 |
| Upload End | Date when uploads close (YYYY-MM-DD) | 2026-04-15 |
| Notes | Additional info displayed on homepage | Mog Park location |

## Security Considerations

1. **Service Account Credentials**: Store JSON file outside web root
2. **Passwords**: Currently stored in plaintext in Sheets (consider hashing in future)
3. **User Tracking**: Uses UUID cookies + server-side metadata file
4. **File Validation**: MIME type checking + size limits
5. **Deletion**: Time-limited deletion (7 days default)
6. **HTTP Headers**: Security headers included (X-Frame-Options, etc.)

## Troubleshooting

### Can't connect to Google Drive

- Verify service account JSON path in `.env`
- Confirm service account email has access to root folder
- Check Google Drive API is enabled in Cloud Console

### Albums not showing

- Verify folder names match exactly (case-sensitive)
- Check Google Sheets has correct configuration
- Clear cache: `rm storage/cache/albums.json`

### File uploads failing

- Check file MIME type is allowed
- Verify file size is under limit
- Ensure write permissions on storage directories
- Check PHP error log: `storage/logs/php.log`

### Cookies not working

- Verify `SESSION_COOKIE_SECURE` matches your protocol (http/https)
- Check `BASE_URL` in `.env`
- Clear browser cookies and try again

## Future Enhancements

- [ ] Bulk download as ZIP
- [ ] Image tagging/metadata
- [ ] Facebook auto-posting
- [ ] Advanced analytics
- [ ] Rate limiting
- [ ] Database storage for metadata (instead of JSON)

## License

© 2026 Porsche Club of America - Diablo Region

## Support

For issues or questions, contact the Diablo Region PCA administrators.
