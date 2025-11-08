# Quick Fix Guide - Supabase File Upload

## The Issue
File uploads fail when Heroku dyno is running because `SUPABASE_SERVICE_ROLE_KEY` isn't set.

## Quick Fix (4 Steps)

### 1. Get Your Service Role Key from .env
```bash
grep SUPABASE_SERVICE_ROLE_KEY .env
```

This will show your key (starts with `eyJhbGciOiJIUzI1NiIs...`)

### 2. Set the Environment Variable on Heroku
```bash
heroku config:set SUPABASE_SERVICE_ROLE_KEY="YOUR_KEY_FROM_ENV_FILE"
```

⚠️ **IMPORTANT**: Replace `YOUR_KEY_FROM_ENV_FILE` with the actual key from your .env file

### 3. Wait for Dyno to Restart
Heroku will automatically restart your dyno after setting the config var (takes ~30 seconds).

### 4. Test
Try uploading a file or run:
```bash
heroku run php artisan supabase:check
```

## What Changed in Your Code
- `app/Livewire/Admin/Files.php` - Now requires service role key
- `app/Livewire/Notes/Index.php` - Now requires service role key
- `app/Console/Commands/CheckSupabaseConfig.php` - New diagnostic command

## Why It Failed Before
Your code was falling back to `SUPABASE_ANON_KEY` which has restricted permissions and can't upload files.

## Verify Heroku Config
```bash
# Check if key is set (will show the key)
heroku config:get SUPABASE_SERVICE_ROLE_KEY

# Or list all config vars
heroku config
```

---

For complete details, see `SUPABASE_FIX_README.md`
