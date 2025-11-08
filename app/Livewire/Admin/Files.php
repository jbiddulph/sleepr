<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Storage;
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
        $this->refreshFiles();
    }

    public function upload(): void
    {
        $this->validate();

        $disk = 'supabase';
        if (!config("filesystems.disks.$disk")) {
            $this->status = __('Supabase disk is not configured.');
            return;
        }

        $bucket = config("filesystems.disks.$disk.bucket") ?? env('SUPABASE_BUCKET');
        $basePublicUrl = config("filesystems.disks.$disk.public_url")
            ?? rtrim(env('SUPABASE_PUBLIC_URL', ''), '/')
            ?? '';
        if (!$basePublicUrl) {
            $supabaseUrl = rtrim(env('SUPABASE_URL', ''), '/');
            if ($supabaseUrl) {
                $basePublicUrl = $supabaseUrl.'/storage/v1/object/public';
            }
        }

        if (!$bucket || !$basePublicUrl) {
            $this->status = __('Missing SUPABASE configuration.');
            return;
        }

        $original = $this->file->getClientOriginalName();
        $directory = trim('uploads/'.now()->format('Y/m/d'), '/');
        $filename = Str::uuid().'-'.$original;
        try {
            $storedPath = $this->file->storePubliclyAs($directory, $filename, $disk);
        } catch (\Throwable $e) {
            report($e);
            $this->status = __('Upload failed: :message', ['message' => $e->getMessage()]);
            $this->publicUrl = null;
            return;
        }

        $this->reset('file');

        $publicUrl = $this->makePublicUrl($storedPath);
        $this->publicUrl = $publicUrl;
        $this->status = __('File uploaded successfully.');
        $this->refreshFiles();
    }

    public function refreshFiles(): void
    {
        $disk = 'supabase';
        $this->loadingFiles = true;

        try {
            if (!config("filesystems.disks.$disk")) {
                $this->files = [];
                return;
            }

            $paths = collect(Storage::disk($disk)->allFiles())
                ->sort()
                ->values();

            $this->files = $paths->map(function (string $path) {
                return [
                    'path' => $path,
                    'name' => basename($path),
                    'url' => $this->makePublicUrl($path),
                ];
            })->all();
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

    private function makePublicUrl(string $path): string
    {
        $disk = config('filesystems.disks.supabase', []);
        $bucket = $disk['bucket'] ?? env('SUPABASE_BUCKET');
        $basePublicUrl = $disk['public_url']
            ?? env('SUPABASE_PUBLIC_URL')
            ?? (trim(env('SUPABASE_URL', '')) ? rtrim(env('SUPABASE_URL', ''), '/').'/storage/v1/object/public' : '');

        if (!$bucket || !$basePublicUrl) {
            return '';
        }

        $encodedBucket = rawurlencode($bucket);
        $encodedPath = collect(explode('/', trim($path, '/')))
            ->map(fn ($segment) => rawurlencode($segment))
            ->join('/');

        return rtrim($basePublicUrl, '/').'/'.$encodedBucket.'/'.$encodedPath;
    }
}


