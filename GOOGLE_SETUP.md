# Google API Setup Guide

Quick step-by-step guide to set up Google APIs for PCA Photo Hub.

## TL;DR Quick Setup (5 minutes)

1. **Get Service Account JSON**: [Google Cloud Console](https://console.cloud.google.com) → Create Project → Enable Drive API → Create Service Account → Generate JSON key
2. **Create Drive Folder**: Make a folder in Google Drive, share with service account
3. **Create Sheets**: Make a Google Sheet with album config, share with service account
4. **Update .env**: Fill in folder ID, sheet ID, and JSON path

---

## Detailed Steps

### 1. Create Google Cloud Project

1. Go to https://console.cloud.google.com/
2. At the top, click "Select a Project"
3. Click "NEW PROJECT"
4. Enter name: **PCA Photo Hub**
5. Click "CREATE"
6. Wait a moment for the project to be created

### 2. Enable Google Drive API

1. In the left sidebar, click **APIs & Services** → **Library**
2. Search for: **Google Drive API**
3. Click on "Google Drive API" in the results
4. Click **ENABLE**

### 3. Enable Google Sheets API

1. Still in APIs & Services, go to **Library**
2. Search for: **Google Sheets API**
3. Click on "Google Sheets API" in the results
4. Click **ENABLE**

### 4. Create Service Account

1. Go to **APIs & Services** → **Credentials**
2. Click **+ CREATE CREDENTIALS** button at top
3. Choose **Service Account**
4. Fill in:
   - **Service account name**: `pca-photo-hub`
   - **Service account ID**: (will auto-fill)
   - **Service account description**: Photo hub for Diablo PCA
5. Click **CREATE AND CONTINUE**
6. On next page, under **Grant this service account access to project**:
   - Select role: **Editor**
   - Click **CONTINUE**
7. Click **DONE**

### 5. Create and Download JSON Key

1. You should see the service account you just created
2. Click on it to open the details page
3. Go to the **KEYS** tab
4. Click **ADD KEY** → **Create new key**
5. Choose **JSON** format
6. Click **CREATE**
7. Your JSON file will download automatically
8. **Save this file securely!** You'll need the path in `.env`

### 6. Get Service Account Email

1. Still in the service account details, look for **Email** field
2. It looks like: `pca-photo-hub@your-project-id.iam.gserviceaccount.com`
3. **Copy this email** - you'll need it to share folders

---

## Google Drive Setup

### Create Root Folder

1. Go to https://drive.google.com/
2. Click **New** → **Folder**
3. Name it: **PCA Photo Hub** (or whatever you prefer)
4. Click **Create**
5. Once created, open the folder
6. Look at the URL: `https://drive.google.com/drive/folders/XXXXXXXXXXXX`
7. **Copy the folder ID** (the part after `/folders/`)

### Share with Service Account

1. In the folder, click the **Share** button (top right)
2. Paste the service account email from step 6 above
3. Give it **Editor** access
4. Click **Share**

---

## Google Sheets Setup

### Create Configuration Sheet

1. Go to https://sheets.google.com/
2. Click **+ Create** → **Blank spreadsheet**
3. Name it: **PCA Photo Hub Config**

### Add Headers

In the first row, add these column headers:

```
Album Name | Password | Upload Start | Upload End | Notes
```

### Add Sample Albums

Add 2-3 sample albums as rows:

| Album Name | Password | Upload Start | Upload End | Notes |
|---|---|---|---|---|
| Spring Autocross 2026 | porsche123 | 2026-03-01 | 2026-04-15 | Mog Park location |
| Summer BBQ Photos | bbqpics2026 | 2026-06-01 | 2026-08-31 | Main event |
| Wine Country Drive | winenight | 2026-09-15 | 2026-10-15 | Northern CA |

### Get Sheet ID and Share

1. Look at the URL: `https://docs.google.com/spreadsheets/d/XXXXXXXXXXXXXX/edit`
2. **Copy the sheet ID** (the part after `/d/`)
3. Click **Share** button (top right)
4. Paste the service account email
5. Give it **Editor** access
6. Click **Share**

---

## Create Google Drive Folders for Albums

For each album in your Sheets, create a matching folder in Google Drive under your PCA Photo Hub root folder:

1. Open the **PCA Photo Hub** root folder in Drive
2. Create a subfolder with the **exact same name** as in the sheet
   - Example: `Spring Autocross 2026`
   - Example: `Summer BBQ Photos`
   - Example: `Wine Country Drive`

**Important**: Folder names must match exactly (case-sensitive)!

---

## Update .env File

Now you have everything. Edit the `.env` file:

```
GOOGLE_SERVICE_ACCOUNT_JSON=/path/to/your/service-account-key.json
GOOGLE_DRIVE_ROOT_FOLDER_ID=your_root_folder_id_here
GOOGLE_SHEETS_CONFIG_ID=your_sheets_id_here
```

---

## Verify Setup

Test your configuration:

```bash
cd pca-photo-hub/public
php -S localhost:8000
```

Visit `http://localhost:8000` and check:
- [ ] Homepage loads without errors
- [ ] Albums from Sheets appear
- [ ] Album status shows correctly

If something fails, check:
1. **PHP error log**: `storage/logs/php.log`
2. **Is Google Drive API enabled?**: Check Cloud Console
3. **Does service account have access?**: Check folder share permissions
4. **JSON path correct in .env?**: Use absolute path
5. **Folder names match?**: Must be exact case match

---

## Troubleshooting

### "Could not authenticate with Google"
- Verify JSON file path is correct
- Verify JSON file is readable (permissions)
- Verify service account has Editor role

### "No albums found"
- Check Google Sheets has data
- Verify album names match Drive folders exactly
- Try: `rm storage/cache/albums.json` to clear cache
- Check PHP error log

### "Cannot read Google Sheets"
- Verify Google Sheets API is enabled
- Verify sheet is shared with service account email
- Check sheet ID in .env is correct

### Folders not showing in Drive
- Make sure you created subfolders INSIDE the root folder
- Verify service account can access them (check share settings)

---

## Next Steps

Once verification passes:
1. Replace sample albums with real event albums
2. Update .env with real folder/sheet IDs
3. Proceed to Phase 2: Frontend development
4. Test homepage with real albums

Need help? Check the main README.md for additional troubleshooting.
