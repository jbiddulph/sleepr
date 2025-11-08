<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Rule as LWRule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use WithFileUploads;

    #[LWRule('required|file|max:51200')] // 50MB
    public $file;

    public string $status = '';
    public ?string $publicUrl = null;
    public array $files = [];
    public bool $loadingFiles = false;

    public function mount(): void
    {
        $this->loadFiles();
    }

    public function upload(): void
    {
        $this->validate();

        [$bucket, $baseUrl, $key, $publicBase] = $this->getSupabaseConfig();
        if (!$bucket || !$baseUrl) {
            $this->status = __('Missing SUPABASE_BUCKET or SUPABASE_URL configuration.');
            return;
        }
        
        if (!$key) {
            $this->status = __('Missing SUPABASE_SERVICE_ROLE_KEY. File uploads require the service role key, not the anon key.');
            Log::error('Upload failed: SUPABASE_SERVICE_ROLE_KEY not configured');
            return;
        }

        $original = $this->file->getClientOriginalName();
        $extension = $this->file->getClientOriginalExtension();
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $safeBasename = Str::slug($basename) ?: 'file';
        $safeFilename = $safeBasename.($extension ? '.'.$extension : '');
        $directory = trim('uploads/'.now()->format('Y/m/d'), '/');
        $filename = Str::uuid().'-'.$safeFilename;
        $path = $directory.'/'.$filename;
        $encodedPath = collect(explode('/', $path))
            ->map(fn ($segment) => rawurlencode($segment))
            ->join('/');
        $endpoint = $baseUrl.'/storage/v1/object/'.rawurlencode($bucket).'/'.$encodedPath;

        try {
            $mime = $this->file->getMimeType() ?: 'application/octet-stream';
            $contents = file_get_contents($this->file->getRealPath());

            Log::info('Supabase upload starting', [
                'user_id' => optional(auth()->user())->id ?? null,
                'bucket' => $bucket,
                'endpoint' => $endpoint,
                'path' => $path,
                'mime' => $mime,
                'size_bytes' => $this->file->getSize(),
                'key_prefix' => substr($key, 0, 8),
                'key_suffix' => substr($key, -8),
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$key,
                'apikey' => $key,
                'Content-Type' => $mime,
                'x-upsert' => 'true',
            ])->put($endpoint, $contents);

            Log::info('Supabase upload response', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);

            if ($response->failed()) {
                $this->status = sprintf(
                    'Upload failed (%s). Response: %s',
                    $response->status(),
                    $response->body() ?: '[empty]'
                );
                $this->publicUrl = null;
                return;
            }

            $this->status = sprintf('Upload succeeded (%s).', $response->status());
        } catch (\Throwable $e) {
            report($e);
            Log::error('Supabase upload exception', [
                'message' => $e->getMessage(),
                'path' => $path,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->status = 'Upload failed with exception: '.$e->getMessage();
            $this->publicUrl = null;
            return;
        }

        $this->reset('file');

        $publicUrl = $this->makePublicUrl($bucket, $publicBase, $path);
        $this->publicUrl = $publicUrl;
        $this->status = __('File uploaded successfully.');
        $this->loadFiles();
    }

    public function loadFiles(): void
    {
        [$bucket, $baseUrl, $key, $publicBase] = $this->getSupabaseConfig();
        if (!$bucket || !$baseUrl) {
            $this->status = __('Missing SUPABASE_BUCKET or SUPABASE_URL configuration.');
            $this->files = [];
            return;
        }
        
        if (!$key) {
            $this->status = __('Missing SUPABASE_SERVICE_ROLE_KEY. Listing files requires the service role key.');
            $this->files = [];
            return;
        }

        $this->loadingFiles = true;

        try {
            $endpoint = $baseUrl.'/storage/v1/object/list/'.rawurlencode($bucket);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$key,
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'prefix' => '',
                'limit' => 1000,
                'offset' => 0,
                'sortBy' => [
                    'column' => 'name',
                    'order' => 'asc',
                ],
            ]);

            if ($response->failed()) {
                $this->status = __('Could not load files: :message', ['message' => $response->body()]);
                $this->files = [];
                return;
            }

            $items = collect($response->json() ?? [])
                ->filter(fn ($item) => empty($item['metadata']['is_directory'] ?? false))
                ->map(function ($item) use ($bucket, $publicBase) {
                    $name = $item['name'] ?? '';
                    $path = $name;
                    return [
                        'name' => basename($name),
                        'path' => $path,
                        'size' => $item['metadata']['size'] ?? null,
                        'updated_at' => $item['updated_at'] ?? null,
                        'url' => $this->makePublicUrl($bucket, $publicBase, $path),
                    ];
                })
                ->values()
                ->all();

            $this->files = $items;
        } catch (\Throwable $e) {
            report($e);
            $this->status = __('Could not load files: :message', ['message' => $e->getMessage()]);
            $this->files = [];
        } finally {
            $this->loadingFiles = false;
        }
    }

    public function render()
    {
        return view('livewire.admin.files');
    }

    private function getSupabaseConfig(): array
    {
        $bucket = env('SUPABASE_BUCKET');
        $baseUrl = rtrim(env('SUPABASE_URL', ''), '/');
        
        // CRITICAL: For file uploads to work, we MUST use the service role key
        // The anon key has restricted permissions and will fail uploads
        $key = env('SUPABASE_SERVICE_ROLE_KEY');
        
        if (!$key) {
            Log::error('SUPABASE_SERVICE_ROLE_KEY is not set! File uploads will fail.', [
                'has_anon_key' => !empty(env('SUPABASE_ANON_KEY')),
                'has_service_key' => !empty(env('SUPABASE_SERVICE_KEY')),
            ]);
        }
        
        $publicBase = rtrim(env('SUPABASE_PUBLIC_URL', ''), '/');

        if (!$publicBase && $baseUrl) {
            $publicBase = $baseUrl.'/storage/v1/object/public';
        }

        return [$bucket, $baseUrl, $key, $publicBase];
    }

    private function makePublicUrl(?string $bucket, ?string $publicBase, string $path): string
    {
        if (!$bucket || !$publicBase) {
            return '';
        }

        $encodedBucket = rawurlencode($bucket);
        $encodedPath = collect(explode('/', trim($path, '/')))
            ->map(fn ($segment) => rawurlencode($segment))
            ->join('/');

        return rtrim($publicBase, '/').'/'.$encodedBucket.'/'.$encodedPath;
    }
}


