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

        $encodedBucket = rawurlencode($bucket);
        $encodedPath = collect(explode('/', trim($storedPath, '/')))
            ->map(fn ($segment) => rawurlencode($segment))
            ->join('/');
        $publicUrl = rtrim($basePublicUrl, '/').'/'.$encodedBucket.'/'.$encodedPath;

        $this->publicUrl = $publicUrl;
        $this->status = __('File uploaded successfully.');
    }

    public function render()
    {
        return view('livewire.admin.files');
    }
}


