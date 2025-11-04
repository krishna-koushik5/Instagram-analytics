# Railway Deployment Guide

## Step 1: Create Railway Account
1. Go to https://railway.app
2. Sign up with GitHub (free)
3. Verify your email

## Step 2: Create New Project
1. Click "New Project"
2. Select "Deploy from GitHub repo"
3. Connect your GitHub account if needed
4. Select your repository (or create one if you haven't)

## Step 3: Configure for PHP
Railway auto-detects PHP, but you may need to create a few files:

### Create `railway.json` (optional - for config):
```json
{
  "build": {
    "builder": "NIXPACKS"
  }
}
```

### Create `Procfile` (tells Railway how to run):
```
web: vendor/bin/heroku-php-apache2
```

OR if you don't have Composer:
```
web: php -S 0.0.0.0:$PORT
```

## Step 4: Update defines.php
Update the `FACEBOOK_REDIRECT_URI` to your Railway URL:
```php
define( 'FACEBOOK_REDIRECT_URI', 'https://your-app-name.railway.app/obtaining_access_token.php' );
```

## Step 5: Deploy
1. Railway will automatically deploy when you push to GitHub
2. Or click "Deploy" in Railway dashboard
3. Get your public URL from Railway dashboard

## Step 6: Update Facebook App Settings
1. Go to https://developers.facebook.com/apps
2. Select your app
3. Settings → Basic
4. Add your Railway URL to "Valid OAuth Redirect URIs"
5. Save changes

## Cost
- $5 free credit per month
- Usually enough for small apps
- Pay only if you exceed (unlikely for this app)

---

## Alternative: ngrok (Instant, Free, Temporary)

If Railway doesn't work, use ngrok:

1. Download ngrok: https://ngrok.com/download
2. Start XAMPP Apache
3. Run: `ngrok http 80`
4. Copy the ngrok URL (e.g., `https://abc123.ngrok.io`)
5. Update `FACEBOOK_REDIRECT_URI` in defines.php
6. Update Facebook App settings with ngrok URL

**Note:** ngrok URL changes every time you restart (unless you have a paid plan)

