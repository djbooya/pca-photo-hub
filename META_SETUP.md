# Meta (Facebook & Instagram) Setup Guide

Publishing is optional. The admin area works for screening and deleting photos without any
of this configured — you only need this to push photos out to Facebook or Instagram.

---

## Read this first: what is and is not possible

**Facebook Groups cannot be posted to.** Meta shut down the Groups API on **22 April 2024**,
removing the `publish_to_groups` permission and every Groups publishing endpoint. No
application can create albums or post photos into a Facebook Group any more — this is why
Buffer, Hootsuite and Zoho all dropped Group posting. There is no workaround; anything
claiming otherwise is driving a logged-in browser session, not an API.

**This publishes to a Facebook Page instead**, which fully supports albums.

**Instagram Stories cannot carry text or tags.** The API can publish a Story, but it cannot
attach a caption, text overlay, sticker or tag to one. So this publishes **feed posts**: one
photo, or a swipeable carousel for several (max 10), where the caption carries your text and
hashtags.

---

## What you will need

- A Facebook **Page** for the club (not just a Group)
- An Instagram **Business** or **Creator** account, linked to that Page
- A Facebook account that is an admin of both

---

## Step 1: Create a Meta app

1. Go to https://developers.facebook.com/apps/
2. **Create App** → choose **Business** → name it e.g. `PCA Photo Hub`
3. Note the **App ID** and **App Secret** (Settings → Basic)

## Step 2: Add products

In the left sidebar, **Add Product**:
- **Facebook Login** (needed only to mint tokens)
- **Instagram Graph API**

## Step 3: Link the Instagram account to the Page

On facebook.com, open your Page → **Settings** → **Linked Accounts** → connect Instagram.
On Instagram, confirm the account is set to **Business** or **Creator**
(Settings → Account type).

## Step 4: Generate a long-lived Page access token

1. Open the [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Pick your app, then **Get Token → Get User Access Token**
3. Tick these permissions:
   - `pages_show_list`
   - `pages_manage_posts`
   - `pages_read_engagement`
   - `instagram_basic`
   - `instagram_content_publish`
4. **Generate Access Token** and approve the dialog
5. Exchange it for a long-lived token:
   ```
   GET https://graph.facebook.com/v21.0/oauth/access_token
       ?grant_type=fb_exchange_token
       &client_id=YOUR_APP_ID
       &client_secret=YOUR_APP_SECRET
       &fb_exchange_token=SHORT_LIVED_TOKEN
   ```
6. Get the **Page** token (this is the one to store — Page tokens derived from a long-lived
   user token do not expire unless the password changes or access is revoked):
   ```
   GET https://graph.facebook.com/v21.0/me/accounts?access_token=LONG_LIVED_USER_TOKEN
   ```
   Find your Page in the response and copy its `access_token` and `id`.

## Step 5: Get the Instagram Business Account ID

```
GET https://graph.facebook.com/v21.0/{PAGE_ID}?fields=instagram_business_account&access_token={PAGE_TOKEN}
```

Copy the `instagram_business_account.id` from the response.

## Step 6: Do you need App Review?

Normally `pages_manage_posts` and `instagram_content_publish` require App Review plus
Business Verification. **You can usually skip both.**

While the app is in **Development mode**, it works fully for anyone holding a role on the
app. So if the person whose token you generated in Step 4 is an **Admin / Developer** of the
Meta app (App Roles → Roles), publishing to your own Page and Instagram account works with
no review at all.

Only pursue App Review if you need people *without* an app role to trigger publishing —
which this tool does not require, since it publishes with one stored token.

## Step 7: Fill in `.env`

```
MEDIA_LINK_SECRET=<paste a long random value>
MEDIA_LINK_TTL_MINUTES=15
PUBLIC_BASE_URL=https://yourdomain.com/pca-photo-hub/public

FACEBOOK_PAGE_ID=<from step 4>
FACEBOOK_PAGE_ACCESS_TOKEN=<Page token from step 4>

INSTAGRAM_BUSINESS_ACCOUNT_ID=<from step 5>
INSTAGRAM_ACCESS_TOKEN=<the same Page token works>

META_GRAPH_VERSION=v21.0
```

Generate the secret with:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

`MEDIA_LINK_SECRET` is **required for publishing**. Meta fetches each photo from a URL rather
than accepting uploaded bytes, so the app mints a short-lived signed link
(`public/media.php`) for each selected photo. Nothing in Google Drive is ever made public,
and the links stop working after `MEDIA_LINK_TTL_MINUTES`.

`PUBLIC_BASE_URL` can be left blank — it is derived from the incoming request — but set it
explicitly if the derived value is ever wrong. **It must be reachable from the public
internet**, because Meta's servers fetch it. Publishing cannot work from `localhost`.

---

## Verifying

1. Sign in at `/admin.php` with the password from column F of the config sheet
2. Open an album with photos, select two, click **Publish to Facebook**
3. Check the Page — the album should exist with your chosen cover first
4. Repeat with **Publish to Instagram**

With `APP_DEBUG=true`, every Graph call and its response is written to
`storage/logs/debug.log`. Access tokens are never logged.

## Troubleshooting

**"Unsupported get request" / code 100**
The Page ID or IG account ID is wrong, or the token lacks access to it.

**"The user is not an admin of the page"**
The token is a *user* token, not a *Page* token. Redo step 4.6.

**"Media could not be fetched" / IG container fails**
Meta cannot reach your `media.php` URL. Confirm `PUBLIC_BASE_URL` is publicly reachable
over HTTPS and that opening a signed link in a browser returns the image.

**Photos upload but the wrong one is the cover**
Facebook makes the first photo uploaded into an album its cover; the app uploads your
chosen cover first. If Facebook later auto-selects a different one, set it manually on the
Page.

**Token suddenly stops working**
Page tokens die if the account password changes or permissions are revoked. Redo step 4.
