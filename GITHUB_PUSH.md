# Push to GitHub - Manual Instructions

## Step 1: Create Repository on GitHub

1. Go to https://github.com/new
2. Enter repository name: **pca-photo-hub**
3. Description: **Photo-sharing platform for Diablo Region of Porsche Club of America**
4. Choose: **Public** (so others can view)
5. Do NOT initialize with README (we already have one)
6. Click **Create repository**

You'll see a page with the repository URL: `https://github.com/djbooya/pca-photo-hub.git`

## Step 2: Add Remote and Push

```bash
cd C:\Users\booya\Nextcloud\claude-code\pca-photo-hub

# Add the remote
git remote add origin https://github.com/djbooya/pca-photo-hub.git

# Rename branch to main (if not already)
git branch -M main

# Push all commits to GitHub
git push -u origin main
```

**First time pushing?** You may be prompted for credentials:
- Use your GitHub username
- For password, create a **Personal Access Token** (Settings → Developer Settings → Personal Access Tokens)

## Step 3: Verify

1. Go to https://github.com/djbooya/pca-photo-hub
2. You should see all files and commits
3. Check that release notes appear in README.md

## What Gets Pushed

✅ All source code  
✅ Configuration templates  
✅ Documentation  
✅ `.gitignore` (secrets won't push)  

❌ `.env` file (not committed, requires manual setup)  
❌ `vendor/` directory (generated on install)  
❌ Google service account JSON (must stay local)  

## Troubleshooting

**"Repository not found"**
- Ensure you created the repo on GitHub first
- Check the URL is correct: `https://github.com/djbooya/pca-photo-hub.git`

**"Authentication failed"**
- Use a Personal Access Token instead of password
- Create at: https://github.com/settings/tokens

**"Updates were rejected"**
- Add `--force` flag if you really want to overwrite: `git push -u origin main --force`
- (This shouldn't be needed for initial push)

## After Pushing

Your code is now on GitHub! You can:
- ✅ Share the link: https://github.com/djbooya/pca-photo-hub
- ✅ Add to other PCA members' favorite repositories
- ✅ Track issues and feature requests
- ✅ Deploy directly from GitHub (via CI/CD)
- ✅ Create releases and tags

## Future Pushes

After the initial setup, pushing new changes is simple:

```bash
git add .
git commit -m "Your message here"
git push
```

No need to repeat the `git remote add` and `git branch -M main` commands — they're only needed once.
