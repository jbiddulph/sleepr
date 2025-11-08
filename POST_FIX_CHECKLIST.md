# Post-Fix Checklist ✅

Use this checklist to ensure everything is working correctly after applying the fix.

## Pre-Deployment Checklist

- [ ] **Review code changes**
  - [ ] Check `app/Livewire/Admin/Files.php`
  - [ ] Check `app/Livewire/Notes/Index.php`
  - [ ] Review new `app/Console/Commands/CheckSupabaseConfig.php`

- [ ] **Test locally**
  ```bash
  php artisan supabase:check
  ```
  Expected: ✅ All checks passed!

- [ ] **Commit changes**
  ```bash
  git add .
  git commit -m "Fix: Require SUPABASE_SERVICE_ROLE_KEY for file uploads"
  git status
  ```

## Deployment Checklist

- [ ] **Push to Heroku**
  ```bash
  git push heroku main
  ```
  Wait for build to complete

- [ ] **Get your service role key**
  ```bash
  grep SUPABASE_SERVICE_ROLE_KEY .env
  ```
  Copy the key value (the part after the =)

- [ ] **Set environment variable on Heroku**
  ```bash
  heroku config:set SUPABASE_SERVICE_ROLE_KEY="PASTE_YOUR_KEY_HERE"
  ```
  Replace PASTE_YOUR_KEY_HERE with the actual key from previous step

- [ ] **Verify environment variables**
  ```bash
  heroku config:get SUPABASE_SERVICE_ROLE_KEY
  heroku config:get SUPABASE_URL
  heroku config:get SUPABASE_BUCKET
  ```
  All three should return values

- [ ] **Wait for dyno restart**
  Check Heroku dashboard or run:
  ```bash
  heroku ps
  ```

## Verification Checklist

- [ ] **Run diagnostic on Heroku**
  ```bash
  heroku run php artisan supabase:check
  ```
  Expected output:
  ```
  ✓ SUPABASE_SERVICE_ROLE_KEY is configured
  ✓ Successfully connected to Supabase Storage
  ✅ All checks passed!
  ```

- [ ] **Test file upload via UI**
  - [ ] Navigate to admin files page
  - [ ] Select a test file
  - [ ] Click upload
  - [ ] Verify success message appears
  - [ ] Check file appears in file list

- [ ] **Verify in Supabase Dashboard**
  - [ ] Log into Supabase dashboard
  - [ ] Go to Storage → Buckets
  - [ ] Check "sleepr" bucket
  - [ ] Confirm uploaded file is there

- [ ] **Check application logs**
  ```bash
  heroku logs --tail
  ```
  Look for:
  - ✅ No errors related to Supabase
  - ✅ Successful upload log entries
  - ❌ No "Missing SUPABASE configuration" errors

## Security Checklist - IMPORTANT

- [ ] **Verify service role key is NOT in Git commits**
  ```bash
  git log --all -p | grep -i "eyJhbGciOiJIUzI1NiIs"
  ```
  Should return nothing

- [ ] **If key was exposed in Git:**
  - [ ] Go to Supabase Dashboard
  - [ ] Settings → API → Reset Service Role Key
  - [ ] Copy new key
  - [ ] Update local .env with new key
  - [ ] Update Heroku: `heroku config:set SUPABASE_SERVICE_ROLE_KEY="NEW_KEY"`
  - [ ] Consider using `git filter-branch` to remove from history
  - [ ] Close GitHub security alert

- [ ] **Service role key is not in client-side code**
  - [ ] Check JavaScript files
  - [ ] Check Blade templates
  - [ ] Key should only be in server-side PHP

## Troubleshooting Checklist

If uploads still fail:

- [ ] **Check Heroku logs for errors**
  ```bash
  heroku logs --tail --source app
  ```

- [ ] **Verify service role key is correct**
  ```bash
  heroku config:get SUPABASE_SERVICE_ROLE_KEY
  ```
  Should start with: `eyJhbGciOiJIUzI1NiIs`

- [ ] **Check Supabase bucket exists**
  - Log into Supabase dashboard
  - Storage → Buckets
  - Verify "sleepr" bucket exists

- [ ] **Restart dynos manually**
  ```bash
  heroku restart
  ```

## Final Success Criteria

✅ **The fix is successful if:**

1. ✅ `heroku run php artisan supabase:check` passes
2. ✅ File uploads work through the UI
3. ✅ No errors in Heroku logs
4. ✅ Uploaded files appear in Supabase Storage
5. ✅ File list loads correctly
6. ✅ Can download/access uploaded files
7. ✅ Service role key is NOT exposed in Git

## Quick Commands Reference

```bash
# Get key from .env
grep SUPABASE_SERVICE_ROLE_KEY .env

# Set on Heroku
heroku config:set SUPABASE_SERVICE_ROLE_KEY="YOUR_KEY"

# Check Supabase config
heroku run php artisan supabase:check

# View logs
heroku logs --tail

# Check environment variables
heroku config

# Restart app
heroku restart
```

---

**Completion Date:** _______________

**Completed By:** _______________

**Notes:**
_____________________________________________
_____________________________________________
