# Quick Fix Guide - Supabase File Upload

## The Issue
File uploads fail when Heroku dyno is running because `SUPABASE_SERVICE_ROLE_KEY` isn't set.

## Quick Fix (3 Steps)

### 1. Set the Environment Variable on Heroku
```bash
heroku config:set SUPABASE_SERVICE_ROLE_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcHJtZWJiYWh6am5yZWtrdnh2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTcwODExNzE5NCwiZXhwIjoyMDIzNjkzMTk0fQ.50H10qHDXcHX8zc9Nua7a1jf1j-VN5ACnHcy6ipwfgU"
```

### 2. Wait for Dyno to Restart
Heroku will automatically restart your dyno after setting the config var (takes ~30 seconds).

### 3. Test
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
heroku config:get SUPABASE_SERVICE_ROLE_KEY
```

Should return: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...` (your service role key)

---

For complete details, see `SUPABASE_FIX_README.md`
