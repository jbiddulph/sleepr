#!/bin/bash
# setup-heroku-supabase.sh
# Sets up required Supabase environment variables on Heroku

echo "🚀 Setting up Supabase configuration on Heroku..."
echo ""

# Check if Heroku CLI is installed
if ! command -v heroku &> /dev/null; then
    echo "❌ Heroku CLI is not installed."
    echo "   Install it from: https://devcenter.heroku.com/articles/heroku-cli"
    exit 1
fi

# IMPORTANT: Replace YOUR_SERVICE_ROLE_KEY_HERE with your actual service role key from .env
echo "📝 Setting SUPABASE_SERVICE_ROLE_KEY..."
echo ""
echo "⚠️  MANUAL STEP REQUIRED:"
echo "    Run this command with your actual service role key from .env:"
echo ""
echo "    heroku config:set SUPABASE_SERVICE_ROLE_KEY=\"YOUR_SERVICE_ROLE_KEY_HERE\""
echo ""
echo "    Get your key from .env file: grep SUPABASE_SERVICE_ROLE_KEY .env"
echo ""
read -p "Press Enter after you've set the key manually..."

echo ""
echo "📝 Setting SUPABASE_URL..."
heroku config:set SUPABASE_URL="https://isprmebbahzjnrekkvxv.supabase.co"

echo ""
echo "📝 Setting SUPABASE_BUCKET..."
heroku config:set SUPABASE_BUCKET="sleepr"

echo ""
echo "✅ Configuration complete!"
echo ""
echo "🔍 Verifying configuration..."
heroku config:get SUPABASE_SERVICE_ROLE_KEY | grep -q "eyJhbGciOiJIUzI1NiIs" && echo "  ✓ SUPABASE_SERVICE_ROLE_KEY is set" || echo "  ✗ SUPABASE_SERVICE_ROLE_KEY not found"
heroku config:get SUPABASE_URL | grep -q "supabase.co" && echo "  ✓ SUPABASE_URL is set" || echo "  ✗ SUPABASE_URL not found"
heroku config:get SUPABASE_BUCKET | grep -q "sleepr" && echo "  ✓ SUPABASE_BUCKET is set" || echo "  ✗ SUPABASE_BUCKET not found"

echo ""
echo "🧪 Testing connection (this may take a moment)..."
heroku run php artisan supabase:check

echo ""
echo "🎉 Setup complete! Your Heroku app should now be able to upload files to Supabase."
