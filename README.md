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
   Album Name | Password | Upload Start | Upload End | Notes | Admin Password
   ```
3. Add your albums as rows:
   ```
   Spring Autocross 2026 | porsche123 | 2026-03-01 | 2026-04-15 | Mog Park location | clubadmin2026
   Summer BBQ Photos | bbqpics2026 | 2026-06-01 | 2026-08-31 | Main event          |
   ```
   Column F is a single global admin password for `/admin.php` — the first non-empty value
   found wins, so it only needs filling in once. Make sure `GOOGLE_SHEETS_CONFIG_RANGE`
   in `.env` covers column F (e.g. `Config!A:F`).
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
├── index.php                   # Redirects to public/ (subdirectory installs)
├── .htaccess                   # Locks down everything at this level except index.php
├── config/
│   ├── config.php              # Configuration loader
│   └── .env.example            # Environment template
├── src/
│   ├── GoogleDriveManager.php   # Google Drive API
│   ├── GoogleSheetsManager.php  # Google Sheets API
│   ├── AlbumManager.php         # Album logic
│   ├── FileUploadHandler.php    # Upload validation
│   ├── SessionManager.php       # User session tracking
│   ├── Logger.php               # Debug logging (APP_DEBUG-gated)
│   ├── AdminAuth.php            # Admin password gate + CSRF
│   ├── MediaLink.php            # Signed short-lived photo URLs for Meta
│   ├── MetaGraph.php            # Meta Graph API client
│   ├── FacebookPublisher.php    # Creates Page albums, uploads photos
│   ├── InstagramPublisher.php   # Feed post / carousel publishing
│   └── ErrorSummarizer.php      # Readable Google API error messages
├── public/
│   ├── index.php               # Homepage
│   ├── album.php               # Album detail page
│   ├── upload.php              # Upload handler
│   ├── delete.php              # Delete handler
│   ├── admin.php               # Admin screening + publishing UI
│   ├── admin_action.php        # Admin delete/publish endpoint
│   ├── media.php               # Signed image proxy (Meta fetches these)
│   ├── init.php                # Bootstrap file
│   ├── css/
│   │   └── style.css           # Responsive styles
│   ├── js/
│   │   ├── upload.js           # Upload logic
│   │   ├── admin.js            # Admin selection + publish dialogs
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

### Admin

- `GET /admin.php` - Sign in, pick an album, screen and publish photos
- `POST /admin_action.php` - Delete selected / publish to Facebook / publish to Instagram
- `GET /media.php?f=&exp=&sig=` - Signed, short-lived image proxy used only so Meta can
  fetch photos during publishing (403s without a valid, unexpired signature)

### AJAX Endpoints

- `POST /upload.php` - Handle file uploads
- `POST /delete.php` - Delete user's uploaded file
- `GET /album.php?id={folder_id}&json=1` - Get album data (JSON)

## Admin Area

Visit `/admin.php` and sign in with the password from column F of the config sheet.

- **Screening** — select any photos in an album and delete them. Admins are not restricted
  to their own uploads or the 7-day member deletion window.
- **Publish to Facebook** — creates an album on the club Facebook **Page** with your chosen
  cover, name and description. Facebook *Groups* cannot be posted to by any app; Meta
  removed that API in April 2024.
- **Publish to Instagram** — posts to the feed as a single image or a carousel (max 10),
  with caption text and optional hashtags. Instagram *Stories* cannot carry text or tags
  through the API.

Deleting works with no extra setup. Publishing needs `MEDIA_LINK_SECRET` plus the Meta
credentials — see **[META_SETUP.md](META_SETUP.md)**.

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

### Turn on debug mode first

Before digging further, set `APP_DEBUG=true` in `.env` and reload the failing page. A green debug panel will appear on-screen showing exactly what `.env` values were loaded, whether the service account JSON validated, and the raw result of every Google API call. The same detail is written to `storage/logs/debug.log`. Set it back to `false` when done -- nothing is logged or shown while it's false.

### Can't connect to Google Drive

- Verify service account JSON path in `.env`
- Confirm service account email has access to root folder
- Check Google Drive API is enabled in Cloud Console

### Using a Shared Drive (Team Drive)?

If your root folder lives inside a **Shared Drive** rather than someone's personal "My Drive", sharing a folder to the service account's email is not always enough on its own -- for full reliability, add the service account as an actual **member of the Shared Drive** (Shared Drive → Manage members → add the `client_email` from your service account JSON, Content Manager role or higher). Sharing an individual subfolder within a Shared Drive can work depending on the Shared Drive's sharing settings, but Drive membership is the setup Google documents as supported. If `storage/logs/debug.log` still shows `folder_count: 0` after confirming Shared Drive membership, double-check `GOOGLE_DRIVE_ROOT_FOLDER_ID` in `.env` is the folder's ID (from its URL), not the Shared Drive's own ID.

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

## Release Notes

### v1.3.0 (Sep 7, 2026)
- **Added:** Password-protected **admin area** at `/admin.php`. The password lives in a new **column F ("Admin Password")** of the config spreadsheet, so officers can rotate it without server access. Widen `GOOGLE_SHEETS_CONFIG_RANGE` to cover column F (e.g. `Config!A:F`); with no password set the admin area stays shut rather than open.
- **Added:** Admins can select any photos in an album and **delete** them for screening — not limited to their own uploads or the 7-day window that applies to members.
- **Added:** **Publish to a Facebook Page album** — prompts for album name (defaulting to the hub album name), optional description, and which selected photo is the cover.
- **Added:** **Publish to Instagram** as a feed post — one photo, or a swipeable carousel for several (max 10) — with caption text and optional hashtags.
- **Added:** `public/media.php`, a short-lived HMAC-signed image proxy. Meta fetches photos by URL rather than accepting uploaded bytes, so each selected photo gets a link that expires in minutes; **nothing in Google Drive is ever made public**. Requires `MEDIA_LINK_SECRET`.
- **Added:** `META_SETUP.md` covering the Meta app, Page token, and Instagram account setup — including how to avoid App Review entirely by using Development mode.
- **Security:** Admin actions require a CSRF token and an authenticated session; logins are rate-limited (5 attempts, then a 15-minute lockout) and compared with `hash_equals()`. The media proxy refuses anything unsigned, expired, outside a configured album folder, or not an image.
- **Note on Facebook Groups:** posting to a Group is **not possible** — Meta removed the Groups API entirely on 2024-04-22, taking `publish_to_groups` and every Groups publishing endpoint with it. Pages are the only supported target.
- **Note on Instagram Stories:** the API cannot attach text, tags or stickers to a Story, so publishing targets the feed, where the caption carries both.

### v1.2.0 (Sep 7, 2026)
- **Added:** The entire album tile is now a single tap target on albums that are open for uploads, rather than just the small button -- the tap area went from roughly 343×48px to 343×339px on a phone. Closed and upcoming albums stay non-clickable.
- **Changed:** Theme now follows diablo-pca.org: a **white header** carrying the black club logo (the previous near-black header made the black logo artwork unreadable), **black** album/detail panels with white text, and the club's signature **gold `#f9ab40`** on every call-to-action, status pill, and accent rule -- sampled directly from that site's buttons.
- **Added:** The official Diablo Region logo, configurable via a new optional `LOGO_URL` in `.env` (defaults to the current lockup, so no `.env` change is required).
- **Fixed (significant):** `getEnvOptional()`/`requireEnv()` chained `$_ENV[$key] ?? getenv($key) ?? $default`. The `??` operator only falls through on `null`, but `getenv()` returns **`false`** for an unset key -- so the default was never reachable and every optional setting absent from `.env` silently became `false`. In practice that meant `MAX_FILE_SIZE_MB` → `(int)false` = `0`, rejecting **every upload** as "too large", and `ALLOWED_MIME_TYPES` → `['']`, permitting no file type at all. Replaced with an explicit `envLookup()` helper.
- **Fixed:** Body links now use a darkened gold (`#9a6410`); the brand gold itself does not meet contrast requirements as text on white.
- **Fixed:** The "Home" and "Back to Albums" links pointed at `/` (the domain root), which breaks under a subdirectory install; both are now relative.
- **Fixed:** Album tiles in a row are now equal height with their buttons aligned, and the previously unused "coming soon" status style is now actually applied to upcoming albums.

### v1.1.4 (Sep 7, 2026)
- **Added:** The root `index.php` redirect now sends explicit `Cache-Control: no-store, no-cache, must-revalidate` headers. A 302 is already non-cacheable per spec, but the CDN in front of this site was observed pinning an older `301` from this URL and replaying it to visitors long after the underlying issue was fixed -- which is very painful to diagnose (HEAD requests showed the correct fresh response while GET requests served the stale cached one).
- **Note:** If you deploy a redirect fix and still see old behavior, suspect CDN caching. Test with a cache-busting query string (`?cb=$(date +%s)`) to force a miss, then purge the CDN cache.

### v1.1.3 (Sep 7, 2026)
- **Fixed:** Redirect loop still occurred behind a CDN/reverse proxy that terminates TLS and forwards to the origin over plain HTTP -- the v1.1.2 redirect used a scheme-relative `Location` header, which Apache reconstructed into an absolute URL using its own (wrong) view of the connection scheme, silently downgrading HTTPS visitors to `http://`, which then bounced back to HTTPS via the host's own HTTP→HTTPS redirect. The root `index.php` redirect now builds a fully-qualified absolute URL itself, respecting `X-Forwarded-Proto`, so the scheme is never ambiguous.
- **Fixed:** `/pca-photo-hub/public/` was returning `403 Forbidden` -- the v1.1.1 root `.htaccess` denied the whole directory by default and relied on `public/.htaccess` overriding that back to allowed, which depends on `Require`/`Order` directives correctly cascading through nested `.htaccess` files and being permitted by `AllowOverride`; this did not behave consistently on this host/CDN. Replaced with an explicit denylist (`config/`, `src/`, `storage/`, `vendor/`, dotfiles, `*.json`/`*.log`/`*.lock`, and the markdown docs) using `mod_rewrite` `[F]`, which is scoped to this directory only and isn't inherited into `public/`, so no override is needed there at all.

### v1.1.2 (Sep 7, 2026)
- **Fixed:** The v1.1.1 subdirectory redirect (root `index.php`) could loop endlessly on some hosts -- it computed the redirect target from `dirname($_SERVER['SCRIPT_NAME'])`, but `SCRIPT_NAME`'s shape for a directory-index request (no filename in the URL) varies by SAPI. On PHP-FPM/LiteSpeed-style setups it can report just the directory itself (e.g. `/pca-photo-hub/`), and `dirname()` on that strips the whole subdirectory segment, sending the redirect to `/public/` at the domain root instead of `/pca-photo-hub/public/` -- which doesn't exist there and can bounce into a loop depending on the host's catch-all handling.
- **Fixed:** Now derives the redirect target from `REQUEST_URI` (what the client actually requested) instead, which is consistent across SAPIs

### v1.1.1 (Sep 7, 2026)
- **Added:** Subdirectory installation support -- visiting the project root (e.g. `https://yourdomain.com/pca-photo-hub/`) now redirects to `public/`, the real application, via a new root-level `index.php`. Needed on shared hosts that only let you point a domain at one fixed document root, with no way to make it `public/` inside a subfolder.
- **Security fix:** Added a root-level `.htaccess` that denies direct access to everything except that redirect -- previously, installing the whole project under the web root (rather than pointing the document root at `public/`) meant `.env`, any service account JSON credentials file, `config/`, `src/`, `storage/`, and `vendor/` were all directly downloadable by URL to anyone who requested them.
- **Fixed:** `public/.htaccess` hardcoded `RewriteBase /`, which resolves relative to the site's true root -- under a subdirectory install this pointed the internal rewrite at the wrong location entirely. Removed; Apache now resolves it relative to wherever `public/` actually lives.
- **Note:** This `.htaccess`-based protection only works if your host honors `.htaccess` (`AllowOverride All`). See the new "Shared Hosting" section in `DEPLOYMENT.md` for how to verify this and for the one deployment method that protects credentials unconditionally (keeping them outside the web-servable tree).

### v1.1.0 (Sep 7, 2026)
- **Added:** Albums configured in Google Sheets that don't yet have a matching Drive folder are now created automatically -- no more manually creating a folder for every new row in the config sheet
- **Behavior:** A folder is only auto-created if the album's upload end date hasn't already passed (or has no end date set); an album whose window already closed before it ever got a folder is skipped rather than creating a folder nobody can use
- **Where:** Implemented in `AlbumManager::loadAndMatchAlbums()`, so it runs whenever albums are loaded (homepage and album page); folder names come directly from the "Album Name" column, created under `GOOGLE_DRIVE_ROOT_FOLDER_ID`
- **Resilient:** If folder creation fails (e.g. a transient API error), it's logged (`APP_DEBUG=true`) and skipped for that request -- retried automatically on the next page load

### v1.0.9 (Sep 7, 2026)
- **Fixed:** Uploading a photo crashed with an uncaught `TypeError: base64_encode(): Argument #1 ($string) must be of type string, resource given` -- `GoogleDriveManager::uploadFile()` passed a file resource handle (`fopen()`) as the multipart upload's `data`, but Google's client library base64-encodes that value internally, and PHP 8's `base64_encode()` rejects anything but a string. Now reads the file's contents into a string first (`file_get_contents()`); fine for uploads capped at 25MB.
- **Hardened:** All `catch` blocks around Google API client calls now catch `\Throwable` instead of `\Exception` -- a `TypeError` (like this one) extends PHP's `Error` class, not `Exception`, so it completely bypassed our error handling and `Logger` calls, crashing uncaught with zero diagnostic trail. They're now caught, logged, and summarized like any other failure.

### v1.0.8 (Sep 7, 2026)
- **Fixed:** Homepage silently stopped rendering right after "Select an event below to upload your photos" whenever an album's dates were served from `storage/cache/albums.json` instead of a fresh Sheets fetch -- a fatal error with `display_errors` off, so the page just truncated with no visible error
- **Root cause:** `GoogleSheetsManager` parsed the Sheet's date columns into `DateTime` objects and then `json_encode()`'d them straight into the cache file. A `DateTime` object round-tripped through JSON does not come back as a `DateTime` -- it comes back as a plain array of its internal representation. On the next request served from cache, `AlbumManager` called `->format()`/`->diff()` on that array and fatal-errored mid-render.
- **Fixed:** Dates are now stored as plain strings in `GoogleSheetsManager` (and therefore in the JSON cache); `AlbumManager` parses them into `DateTime` objects itself, in memory, on every request -- never storing the objects back into anything that gets serialized
- **Hardened:** The new date parser also tolerates a stale pre-fix cache file (which still has the broken array shape) by logging a warning and treating it as unset, instead of crashing on a `TypeError`
- **Note:** After deploying, delete `storage/cache/albums.json` (or wait 5 minutes for it to expire) so a fresh, correctly-shaped cache is written

### v1.0.7 (Sep 7, 2026)
- **Fixed:** Root folder and album folders returning zero results (`listRootFolders succeeded {"folder_count":0}` in the debug log) when the root folder lives inside a **Shared Drive** rather than a personal "My Drive" -- Google Drive's `files.list` silently scopes to "My Drive" only unless `supportsAllDrives` and `includeItemsFromAllDrives` are explicitly passed, regardless of sharing permissions being correct
- **Fixed:** Added `supportsAllDrives: true` to every Drive API call (list, upload, delete, get metadata, create folder) so the app works correctly with folders/files stored in a Shared Drive
- **Added:** A warning is now logged (visible with `APP_DEBUG=true`) whenever the root folder query returns zero subfolders, listing the likely causes to check next

### v1.0.6 (Sep 7, 2026)
- **Fixed (root cause):** The persistent `GOOGLE_SERVICE_ACCOUNT_JSON is not set in .env` error, found using the v1.0.5 debug logs, was caused by naming a helper function `getEnv()`. PHP function names are case-insensitive, so `getEnv()` is literally the same function as PHP's built-in `getenv()` and cannot be overridden -- the `function_exists('getEnv')` guard added in v1.0.1 was silently detecting the built-in and skipping our version, so every `getEnv(...)` call was secretly invoking PHP's real `getenv()`, which only reads the OS process environment and knows nothing about `.env` values. It always returned `false`.
- **Fixed:** Renamed the helper to `requireEnv()` throughout `config.php` so it no longer collides with any PHP built-in
- **Credit:** Found by reading the v1.0.5 debug panel, which showed `GOOGLE_SERVICE_ACCOUNT_JSON` resolving correctly via `getEnvOptional()` (no collision) but the final config value still showing empty -- pointing straight at the one place still using the broken `getEnv()`

### v1.0.5 (Sep 7, 2026)
- **Added:** Diagnostic debug logging, gated entirely behind `APP_DEBUG=true` in `.env`
- **Added:** `src/Logger.php` -- writes detailed step-by-step logs to `storage/logs/debug.log` (`.env` loading, Google auth validation, every Drive/Sheets API call) and renders a readable on-screen debug panel on the homepage and album page
- **Added:** `src/ErrorSummarizer.php` -- detects when Google returns an HTML error page instead of API data and shows a short, actionable message instead of dumping the raw HTML; the full raw response is still captured in the debug log
- **Added:** AJAX endpoints (`upload.php`, `delete.php`) include a `debug` array in their JSON response when `APP_DEBUG=true`
- **Note:** When `APP_DEBUG=false` (the default), none of this is written to disk or shown on screen -- zero overhead and zero exposure in production
- **How to use:** Set `APP_DEBUG=true` in `.env`, reload the page that's failing, and read the green debug panel (or `storage/logs/debug.log`) to see exactly which `.env` value or Google API call is the problem
- **Fixed:** `session_start()` was never called anywhere, so the `$_SESSION` reads/writes used for album password verification in `album.php` silently failed (and would throw their own PHP warnings) -- now started once in `init.php`

### v1.0.4 (Sep 7, 2026)
- **Fixed:** `.env` values were silently ignored whenever the hosting platform pre-declared the same environment variable as an empty placeholder (common with control-panel PHP env var managers) -- `isset($_ENV[$key])` was `true` for a blank value, so the real `.env` value never loaded
- **Fixed:** `.env` values now take precedence over any existing environment entry that is empty, `false`, or unset; only a genuinely non-empty pre-existing value is left alone
- **Symptom this fixes:** `GOOGLE_SERVICE_ACCOUNT_JSON is not set in .env` even though the `.env` file has a correct, non-empty value on that line

### v1.0.3 (Sep 7, 2026)
- **Fixed:** `.env` parsing no longer uses `parse_ini_file()`, which threw "syntax error, unexpected '('" on comment lines containing parentheses (e.g. `# ...(download from Google Cloud Console)`) and silently broke every config value on failure
- **Fixed:** Replaced with a hand-rolled line parser that safely skips `#`/`;` comments and handles special characters, colons, and quoted values in `.env`
- **Fixed:** `date_default_timezone_set()` now falls back to `America/Los_Angeles` if the timezone config is empty, instead of throwing a notice for an invalid empty timezone ID

### v1.0.2 (Sep 7, 2026)
- **Fixed:** Service account JSON is now validated (exists, readable, valid JSON, has required fields) before being handed to the Google client
- **Fixed:** Previously a bad/missing credentials path failed silently inside the Google API client, producing "Trying to access array offset on false" warnings and a broken auth request that Google redirected to an HTML error page instead of a clean API error
- **Improved:** Auth failures on `GoogleDriveManager` and `GoogleSheetsManager` now throw a clear, actionable exception naming the exact problem (missing file, unreadable, invalid JSON, or missing fields)

### v1.0.1 (Sep 6, 2026)
- **Fixed:** "Cannot redeclare getEnv()" fatal error on shared hosting when `config.php` is included more than once per request
- **Fixed:** Wrapped `getEnv()` and `getEnvOptional()` in `function_exists()` guards for hosting compatibility

### v1.0.0 (Aug 30, 2026)
- **Added:** Complete photo-sharing platform with drag-and-drop upload
- **Added:** Password-protected album support
- **Added:** User file deletion tracking with 7-day window
- **Added:** Google Drive and Google Sheets integration
- **Added:** Mobile-responsive design with Diablo PCA branding
- **Added:** Comprehensive documentation and deployment guides
- **Added:** Session management with UUID-based user tracking
- **Added:** Album visibility control via date ranges in Sheets
- **Added:** Multi-file upload with progress tracking
- **Features:** JPG/PNG/WebP support, max 25 MB per file
- **Status:** Production Ready ✅
- **Repository:** [GitHub - PCA Photo Hub](https://github.com/djbooya/pca-photo-hub)

## Future Enhancements

- [ ] Bulk download as ZIP
- [ ] Image tagging/metadata
- [ ] Facebook auto-posting integration
- [ ] Advanced analytics and statistics
- [ ] Rate limiting per IP
- [ ] Database storage for metadata (PostgreSQL/MySQL)
- [ ] Image compression on upload
- [ ] WebP conversion for browser optimization
- [ ] Advanced search and filtering
- [ ] Batch operations (select multiple for delete)

## License

© 2026 Porsche Club of America - Diablo Region

## Support

For issues or questions, contact the Diablo Region PCA administrators.

---

**Version:** 1.3.0 | **Last Updated:** Sep 7, 2026 | **Status:** Production Ready ✅
