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
Get the key from your local `.env` file:

```bash
grep SUPABASE_SERVICE_ROLE_KEY .env
```

The key will look like: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...` (long string)

### Step 2: Set it on Heroku

**Option A: Via Heroku CLI**
```bash
heroku config:set SUPABASE_SERVICE_ROLE_KEY="YOUR_ACTUAL_KEY_FROM_ENV"
```

Replace `YOUR_ACTUAL_KEY_FROM_ENV` with the actual value from your .env file.

**Option B: Via Heroku Dashboard**
1. Go to your app's dashboard on Heroku
2. Navigate to Settings → Config Vars
3. Click "Reveal Config Vars"
4. Add a new variable:
   - KEY: `SUPABASE_SERVICE_ROLE_KEY`
   - VALUE: (paste your service role key from .env)

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

## How to Rotate Your Supabase Service Role Key

If your key was exposed (like in a Git commit), you MUST rotate it:

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Select your project
3. Go to Settings → API
4. Under "Service Role Key" section, click "Reset Service Role Key"
5. Copy the new key
6. Update your local .env file
7. Update Heroku: `heroku config:set SUPABASE_SERVICE_ROLE_KEY="NEW_KEY"`
8. Test: `heroku run php artisan supabase:check`

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
