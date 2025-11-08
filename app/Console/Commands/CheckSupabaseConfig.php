<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckSupabaseConfig extends Command
{
    protected $signature = 'supabase:check';
    protected $description = 'Check Supabase configuration and test connectivity';

    public function handle(): int
    {
        $this->info('Checking Supabase Configuration...');
        $this->newLine();

        // Check environment variables
        $bucket = env('SUPABASE_BUCKET');
        $url = env('SUPABASE_URL');
        $serviceRoleKey = env('SUPABASE_SERVICE_ROLE_KEY');
        $serviceKey = env('SUPABASE_SERVICE_KEY');
        $anonKey = env('SUPABASE_ANON_KEY');

        $this->table(
            ['Variable', 'Status', 'Value'],
            [
                ['SUPABASE_URL', $url ? '✓ Set' : '✗ Missing', $url ? substr($url, 0, 40).'...' : '-'],
                ['SUPABASE_BUCKET', $bucket ? '✓ Set' : '✗ Missing', $bucket ?: '-'],
                ['SUPABASE_SERVICE_ROLE_KEY', $serviceRoleKey ? '✓ Set' : '✗ Missing', $serviceRoleKey ? substr($serviceRoleKey, 0, 20).'...' : '-'],
                ['SUPABASE_SERVICE_KEY', $serviceKey ? '✓ Set' : '✗ Missing', $serviceKey ? substr($serviceKey, 0, 20).'...' : '-'],
                ['SUPABASE_ANON_KEY', $anonKey ? '✓ Set' : '✗ Missing', $anonKey ? substr($anonKey, 0, 20).'...' : '-'],
            ]
        );

        $this->newLine();

        // Check critical requirements
        if (!$serviceRoleKey) {
            $this->error('❌ CRITICAL: SUPABASE_SERVICE_ROLE_KEY is not set!');
            $this->warn('   File uploads REQUIRE the service role key.');
            $this->warn('   The anon key has restricted permissions and will fail.');
            $this->newLine();
            $this->info('To fix on Heroku, run:');
            $this->line('  heroku config:set SUPABASE_SERVICE_ROLE_KEY="your-service-role-key"');
            $this->newLine();
            return self::FAILURE;
        }

        $this->info('✓ SUPABASE_SERVICE_ROLE_KEY is configured');
        $this->newLine();

        // Test connectivity
        if ($url && $bucket && $serviceRoleKey) {
            $this->info('Testing Supabase Storage API connectivity...');
            
            try {
                $endpoint = rtrim($url, '/').'/storage/v1/object/list/'.rawurlencode($bucket);
                $response = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer '.$serviceRoleKey,
                    'apikey' => $serviceRoleKey,
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'prefix' => '',
                    'limit' => 1,
                ]);

                if ($response->successful()) {
                    $this->info('✓ Successfully connected to Supabase Storage');
                    $this->info("  Bucket: {$bucket}");
                    $files = $response->json();
                    $this->info('  Files in bucket: '.count($files ?: []));
                } else {
                    $this->error('✗ Failed to connect to Supabase Storage');
                    $this->error("  Status: {$response->status()}");
                    $this->error("  Response: {$response->body()}");
                    return self::FAILURE;
                }
            } catch (\Throwable $e) {
                $this->error('✗ Exception connecting to Supabase: '.$e->getMessage());
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('✅ All checks passed! Supabase is properly configured.');
        return self::SUCCESS;
    }
}
