# 📋 Setup Guide: Getting Instagram Page Insights

This guide will help you set up and use this tool to get insights for multiple Instagram pages.

## 🚀 Quick Start Steps

### Step 1: Configure Your Facebook App Credentials

1. Open `defines.php` file
2. Replace the placeholder values with your actual credentials:

```php
define( 'FACEBOOK_APP_ID', 'YOUR-APP-ID' );           // Your Facebook App ID
define( 'FACEBOOK_APP_SECRET', 'YOUR-APP-SECRED' );   // Your Facebook App Secret
define( 'FACEBOOK_REDIRECT_URI', 'YOUR-REDIRECT-URI' ); // Your redirect URI
define( 'ENDPOINT_BASE', 'https://graph.facebook.com/v5.0/' );
```

### Step 2: Get Your Access Token

**Option A: Using the provided script (Recommended)**

1. Open `obtaining_access_token.php` in your browser
2. Click "Login With Facebook"
3. Authorize the required permissions
4. Copy the long-lived access token that appears
5. Paste it into `defines.php`:

```php
$accessToken = 'YOUR-LONG-LIVED-ACCESS-TOKEN-HERE';
```

**Option B: Using Graph API Explorer**

1. Go to https://developers.facebook.com/tools/explorer/
2. Select your app
3. Generate a token with these permissions:
   - `instagram_basic`
   - `instagram_manage_insights`
   - `pages_show_list`
   - `pages_read_engagement`
   - `business_management`

### Step 3: Get Your Page ID

1. Open `get_pages.php` in your browser
2. It will show you all Facebook pages you manage
3. Copy the Page ID and paste it into `defines.php`:

```php
$pageId = 'YOUR-PAGE-ID';
```

### Step 4: Get Your Instagram Business Account ID

1. Open `get_instagram_account_id.php` in your browser
2. It will show your Instagram Business Account ID
3. Copy it and paste into `defines.php`:

```php
$instagramAccountId = 'YOUR-INSTAGRAM-ACCOUNT-ID';
```

### Step 5: Add Instagram Usernames to Analyze

1. Open `get_multiple_insights.php`
2. Find the `$instagramUsernames` array at the top
3. Add the Instagram usernames you want to analyze (without @ symbol):

```php
$instagramUsernames = [
	'nike',      // Example: Nike
	'adidas',    // Example: Adidas
	'username3', // Add more as needed
];
```

### Step 6: Run the Insights Script

1. Open `get_multiple_insights.php` in your web browser
2. You'll see insights for all the accounts you added

## 📊 What Insights You Can Get

### For Your Own Accounts (Accounts You Own/Manage):
- **Follower Count** - Daily follower count
- **Impressions** - How many times your content was seen
- **Profile Views** - How many times your profile was viewed
- **Reach** - How many unique accounts saw your content

### For Other Accounts (Public Data Only):
- **Followers Count** - Public follower count
- **Posts Count** - Number of posts
- **Following Count** - Number of accounts they follow
- **Profile Picture** - Profile image URL
- **Biography** - Account bio
- **Website** - Website URL (if available)

## ⚠️ Important Notes

1. **Ownership Required**: You can only get detailed insights (impressions, reach, profile views) for Instagram accounts that you own or manage through your Facebook Page.

2. **Business Account**: The account must be an Instagram Business or Creator account, not a personal account.

3. **Permissions**: Make sure your access token has the `instagram_manage_insights` permission.

4. **API Version**: The code uses API v5.0. If you need a newer version, update `ENDPOINT_BASE` in `defines.php`.

## 🔧 Troubleshooting

### Error: "Invalid OAuth access token"
- Your access token may have expired
- Generate a new long-lived access token

### Error: "User does not have permission"
- Make sure you have the correct permissions in your access token
- The account must be a Business/Creator account

### Error: "Could not find business account"
- The username might be incorrect
- The account might not be a Business account
- You might not have permission to access it

### No Insights Showing
- You can only get detailed insights for accounts you own
- For other accounts, only public data is available

## 📁 File Structure

- `defines.php` - Your configuration file (credentials)
- `get_multiple_insights.php` - Main script to get insights for multiple accounts
- `obtaining_access_token.php` - Helper to get access token
- `get_pages.php` - Helper to get your Facebook pages
- `get_instagram_account_id.php` - Helper to get Instagram account ID
- `insights.php` - Original single account insights script

## 🎯 Next Steps

1. Complete all setup steps above
2. Add Instagram usernames to `get_multiple_insights.php`
3. Open the file in your browser to see the insights
4. Customize the script as needed for your specific requirements

