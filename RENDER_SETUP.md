# Render Deployment Guide

## Step 1: Create Render Account
1. Go to https://render.com
2. Sign up with GitHub (free)
3. Verify your email

## Step 2: Connect GitHub Repository
1. Click "New +" → "Web Service"
2. Connect your GitHub account if needed
3. Select your repository

## Step 3: Configure Render Service
1. **Name**: `instagram-analytics` (or any name you want)
2. **Environment**: `Docker`
3. **Dockerfile Path**: `./Dockerfile` (should auto-detect)
4. **Region**: Choose closest to you
5. **Branch**: `main` (or your default branch)

## Step 4: Set Environment Variables (Optional)
You can set these in Render dashboard under "Environment":
- `PORT` - Render sets this automatically
- Any other vars you need

## Step 5: Deploy
1. Click "Create Web Service"
2. Render will build and deploy automatically
3. Wait for build to complete (takes 5-10 minutes first time)

## Step 6: Get Your Render URL
1. Once deployed, you'll get a URL like: `https://instagram-analytics.onrender.com`
2. Copy this URL

## Step 7: Update defines.php
Update the `FACEBOOK_REDIRECT_URI` in `defines.php`:
```php
define( 'FACEBOOK_REDIRECT_URI', 'https://your-app-name.onrender.com/obtaining_access_token.php' );
```

## Step 8: Update Facebook App Settings
1. Go to https://developers.facebook.com/apps
2. Select your app (ID: 811119178200956)
3. Settings → Basic
4. Add your Render URL to "Valid OAuth Redirect URIs":
   - `https://your-app-name.onrender.com/obtaining_access_token.php`
5. Save changes

## Step 9: Test Your App
1. Visit: `https://your-app-name.onrender.com/get_multiple_insights.php`
2. Should see your Instagram analytics!

---

## Render Free Tier
- **Free forever** (with limitations)
- 750 hours/month free (enough for 24/7)
- Auto-sleeps after 15 minutes of inactivity
- First request after sleep takes ~30 seconds to wake up

## Important Notes
- **First deploy takes 5-10 minutes** (building Docker image)
- **Free tier sleeps after 15 min inactivity** - first request will be slow
- **Auto-deploys** on every git push
- **Logs available** in Render dashboard

---

## Troubleshooting

### Build fails?
- Check Dockerfile is in root directory
- Check render.yaml (optional, can deploy without it)

### App doesn't work?
- Check logs in Render dashboard
- Verify PORT is set correctly (Render sets it automatically)
- Check Facebook App settings have correct redirect URI

### Slow first request?
- Free tier sleeps after 15 min inactivity
- First request wakes up the service (takes ~30 seconds)
- Subsequent requests are fast

---

## Files Created
- `Dockerfile` - Docker configuration for PHP
- `.dockerignore` - Files to exclude from Docker build
- `render.yaml` - Render configuration (optional)
- `RENDER_SETUP.md` - This guide

---

Good luck! 🚀

