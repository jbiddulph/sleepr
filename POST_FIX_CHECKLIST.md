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

- [ ] **Set environment variable**
  
  Choose ONE method:
  
  **Method A: Automated Script** (Recommended)
  ```bash
  ./setup-heroku-supabase.sh
  ```
  
  **Method B: Manual Command**
  ```bash
  heroku config:set SUPABASE_SERVICE_ROLE_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImlzcHJtZWJiYWh6am5yZWtrdnh2Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTcwODExNzE5NCwiZXhwIjoyMDIzNjkzMTk0fQ.50H10qHDXcHX8zc9Nua7a1jf1j-VN5ACnHcy6ipwfgU"
  ```

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

- [ ] **Check bucket policies**
  - Supabase Dashboard → Storage → sleepr → Policies
  - Verify policies don't block uploads (service role key should bypass these anyway)

- [ ] **Test with curl**
  ```bash
  heroku run bash
  # Then in Heroku console:
  php artisan tinker
  ```
  ```php
  $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_ROLE_KEY'),
      'apikey' => env('SUPABASE_SERVICE_ROLE_KEY'),
  ])->post(env('SUPABASE_URL') . '/storage/v1/object/list/sleepr', ['limit' => 1]);
  echo $response->status(); // Should be 200
  ```

- [ ] **Check Laravel logs**
  ```bash
  heroku run cat storage/logs/laravel.log
  ```

- [ ] **Restart dynos manually**
  ```bash
  heroku restart
  ```

## Performance Checklist

- [ ] **Monitor upload times**
  - Small files (< 1MB) should upload in < 5 seconds
  - Large files (> 10MB) may take longer

- [ ] **Check dyno performance**
  ```bash
  heroku ps
  ```

- [ ] **Monitor error rates**
  ```bash
  heroku logs --tail | grep -i error
  ```

## Security Checklist

- [ ] **Service role key is not in Git**
  ```bash
  git log --all --full-history --source -- "*SUPABASE*"
  ```
  Should not show service role key in commits

- [ ] **Service role key is not in client-side code**
  - Check JavaScript files
  - Check Blade templates
  - Key should only be in server-side PHP

- [ ] **Environment variables are secure**
  ```bash
  heroku config
  ```
  Verify no sensitive keys are exposed

## Documentation Checklist

- [ ] **Team is informed**
  - Share this fix with team members
  - Update project documentation

- [ ] **README updated** (if applicable)
  - Add note about required environment variables
  - Link to setup instructions

## Final Success Criteria

✅ **The fix is successful if:**

1. ✅ `heroku run php artisan supabase:check` passes
2. ✅ File uploads work through the UI
3. ✅ No errors in Heroku logs
4. ✅ Uploaded files appear in Supabase Storage
5. ✅ File list loads correctly
6. ✅ Can download/access uploaded files

## Quick Commands Reference

```bash
# Check Supabase config
heroku run php artisan supabase:check

# View logs
heroku logs --tail

# Check environment variables
heroku config

# Restart app
heroku restart

# Open app in browser
heroku open

# Access Heroku bash
heroku run bash
```

---

**Completion Date:** _______________

**Completed By:** _______________

**Notes:**
_____________________________________________
_____________________________________________
_____________________________________________
