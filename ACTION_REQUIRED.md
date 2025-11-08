# IMMEDIATE ACTIONS REQUIRED

## 🚨 CRITICAL - DO THIS FIRST 🚨

Your Supabase service role key was exposed in Git commit `ceb2d19`. I've removed it from the files, but you MUST rotate it immediately.

## Step-by-Step Action Plan

### 1. Rotate Your Supabase Service Role Key (DO NOW)

```
1. Visit: https://app.supabase.com
2. Select project: isprmebbahzjnrekkvxv
3. Go to: Settings → API
4. Find: Service Role Key section
5. Click: "Reset Service Role Key"
6. Copy the NEW key
```

### 2. Update Your Local .env File

```bash
# Open your .env file
nano .env
# or
code .env

# Replace the SUPABASE_SERVICE_ROLE_KEY value with the NEW key from step 1
```

### 3. Update Heroku

```bash
# Set the NEW key on Heroku
heroku config:set SUPABASE_SERVICE_ROLE_KEY="PASTE_NEW_KEY_HERE"
```

### 4. Test Everything

```bash
# Test locally
php artisan supabase:check

# Test on Heroku  
heroku run php artisan supabase:check

# Try uploading a file through your app
```

### 5. Close GitHub Security Alert

```
1. Go to: https://github.com/jbiddulph/sleepr/security
2. Find: Supabase Service Key alert
3. Click on it
4. Click: "Close as" → "Revoked"
5. Add note: "Key rotated in Supabase Dashboard"
```

## What I've Done

✅ Fixed the file upload code to require service role key
✅ Created diagnostic command: `php artisan supabase:check`
✅ Removed all exposed keys from documentation files
✅ Replaced keys with secure placeholders
✅ Committed sanitized files to Git
✅ Pushed changes to GitHub

## What You Need to Do

❌ Rotate the service role key in Supabase (CRITICAL)
❌ Update local .env with new key
❌ Update Heroku with new key
❌ Test the application
❌ Close GitHub security alert

## Files Updated (No Longer Contain Secrets)

- `setup-heroku-supabase.sh` - Now prompts for manual key entry
- `SUPABASE_FIX_README.md` - Uses placeholders
- `QUICK_FIX.md` - Uses placeholders
- `CHANGES_SUMMARY.md` - Uses placeholders
- `POST_FIX_CHECKLIST.md` - Uses placeholders
- `VISUAL_EXPLANATION.md` - Uses placeholders
- `SECURITY_NOTICE.md` - NEW: Detailed rotation instructions

## Quick Commands Reference

```bash
# Get NEW key from Supabase Dashboard, then:

# Update local
nano .env
# (paste new key)

# Update Heroku
heroku config:set SUPABASE_SERVICE_ROLE_KEY="NEW_KEY"

# Test
php artisan supabase:check
heroku run php artisan supabase:check
```

## Need Help?

Read `SECURITY_NOTICE.md` for complete instructions.

## Time Estimate

- Rotating key: 2 minutes
- Updating .env: 30 seconds  
- Updating Heroku: 30 seconds
- Testing: 2 minutes
- Closing alert: 1 minute

**Total: ~6 minutes to secure your application**

---

**DO NOT DELAY - Rotate your key now!**

The exposed key has full administrative access to your Supabase project.
