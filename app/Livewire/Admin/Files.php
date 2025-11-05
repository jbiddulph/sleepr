<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Rule as LWRule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use WithFileUploads;

    #[LWRule('required|file|max:51200')] // 50MB
    public $file;

    public string $status = '';

    public function upload(): void
    {
        $this->validate();

        $bucket = config('filesystems.disks.supabase.bucket') ?? env('SUPABASE_BUCKET');
        $url = rtrim(env('SUPABASE_URL', ''), '/');
        $key = env('SUPABASE_SERVICE_KEY'); // require service key for uploads
        if (!$bucket || !$url || !$key) {
            $this->status = __('Missing SUPABASE configuration.');
            return;
        }

        $original = $this->file->getClientOriginalName();
        $path = 'uploads/'.date('Y/m/d/').uniqid().'-'.$original;
        $endpoint = $url.'/storage/v1/object/'.rawurlencode($bucket).'/'.$path;

        try {
            $contents = file_get_contents($this->file->getRealPath());
            $mime = $this->file->getMimeType();
            $resp = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => $mime ?: 'application/octet-stream',
                'x-upsert' => 'true',
            ])->put($endpoint, $contents);

            if ($resp->failed()) {
                $this->status = __('Upload failed.');
                return;
            }

            $publicUrl = rtrim($url, '/').'/storage/v1/object/public/'.rawurlencode($bucket).'/'.$path;
            $this->reset('file');
            $this->status = __('Uploaded: ').$publicUrl;
        } catch (\Throwable $e) {
            $this->status = __('Error: ').$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.admin.files');
    }
}


