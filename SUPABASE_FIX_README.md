# Supabase File Upload Fix

## The Problem

Your application was unable to upload files to Supabase Storage when the Heroku dyno was running, but it worked locally with Tinker when the dyno was down.

**Root Cause:** The code was falling back to `SUPABASE_ANON_KEY` when `SUPABASE_SERVICE_ROLE_KEY` wasn't set. The anon key has restricted permissions based on Row Level Security (RLS) policies and cannot upload files to storage.

## What Was Fixed

### 1. Updated File Upload Components
- **`app/Livewire/Admin/Files.php`**: Now requires `SUPABASE_SERVICE_ROLE_KEY` explicitly
- **`app/Livewire/Notes/Index.php`**: Same fix for the Notes component

### 2. Added Better Error Messages
The code now clearly indicates when the service role key is missing instead of silently falling back to anon key.

### 3. Created Diagnostic Command
A new Artisan command to check your Supabase configuration:

```bash
php artisan supabase:check
```

## How to Fix on Heroku

Your Heroku app needs the `SUPABASE_SERVICE_ROLE_KEY` environment variable. Here's how to set it:

### Step 1: Get Your Service Role Key
From your `.env` file, the service role key is:
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcHJtZWJiYWh6am5yZWtrdnh2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTcwODExNzE5NCwiZXhwIjoyMDIzNjkzMTk0fQ.50H10qHDXcHX8zc9Nua7a1jf1j-VN5ACnHcy6ipwfgU
```

### Step 2: Set it on Heroku

**Option A: Via Heroku CLI**
```bash
heroku config:set SUPABASE_SERVICE_ROLE_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcHJtZWJiYWh6am5yZWtrdnh2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTcwODExNzE5NCwiZXhwIjoyMDIzNjkzMTk0fQ.50H10qHDXcHX8zc9Nua7a1jf1j-VN5ACnHcy6ipwfgU"
```

**Option B: Via Heroku Dashboard**
1. Go to your app's dashboard on Heroku
2. Navigate to Settings → Config Vars
3. Click "Reveal Config Vars"
4. Add a new variable:
   - KEY: `SUPABASE_SERVICE_ROLE_KEY`
   - VALUE: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcHJtZWJiYWh6am5yZWtrdnh2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTcwODExNzE5NCwiZXhwIjoyMDIzNjkzMTk0fQ.50H10qHDXcHX8zc9Nua7a1jf1j-VN5ACnHcy6ipwfgU`

### Step 3: Verify Configuration

After deploying, you can check the configuration:

```bash
# Locally
php artisan supabase:check

# On Heroku
heroku run php artisan supabase:check
```

## Why Service Role Key is Required

| Key Type | Purpose | Can Upload Files? |
|----------|---------|-------------------|
| **Anon Key** | Public client access with RLS policies | ❌ No (restricted by RLS) |
| **Service Role Key** | Server-side operations, bypasses RLS | ✅ Yes (full access) |

The service role key bypasses Row Level Security policies and has full access to your Supabase project. Keep it secret and only use it server-side.

## Testing the Fix

1. Set the environment variable on Heroku
2. Wait for the dyno to restart
3. Try uploading a file through your application
4. Check the logs if it fails:
   ```bash
   heroku logs --tail
   ```

## Security Note

⚠️ **IMPORTANT**: The service role key has full access to your Supabase project. 
- Never expose it in client-side code
- Never commit it to public repositories
- Only use it in server-side code (backend/API)
- Rotate it if it's ever compromised

## Additional Files to Check on Heroku

Make sure these are also set on Heroku:
```bash
heroku config:get SUPABASE_URL
heroku config:get SUPABASE_BUCKET
heroku config:get SUPABASE_SERVICE_ROLE_KEY
```

All three should return values. If any are missing, set them:
```bash
heroku config:set SUPABASE_URL="https://isprmebbahzjnrekkvxv.supabase.co"
heroku config:set SUPABASE_BUCKET="sleepr"
```

## Questions?

If uploads still fail after setting the environment variable:
1. Run `heroku run php artisan supabase:check` to diagnose
2. Check Heroku logs: `heroku logs --tail`
3. Verify the bucket "sleepr" exists in your Supabase project
4. Check bucket policies in Supabase dashboard (Storage → Policies)
