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
        $key = config('filesystems.disks.supabase.service_key')
            ?? env('SUPABASE_SERVICE_KEY')
            ?? env('SUPABASE_SERVICE_ROLE_KEY')
            ?? env('SUPABASE_ANON_KEY');
        logger()->info('Supabase upload invoked', [
            'bucket' => $bucket,
            'url' => $url,
            'has_key' => !empty($key),
            'user_id' => optional(Auth::user())->id,
        ]);
        if (!$bucket || !$url || !$key) {
            $this->status = __('Missing SUPABASE configuration.');
            logger()->warning('Supabase upload aborted due to missing config', [
                'bucket' => $bucket,
                'url' => $url,
                'has_key' => !empty($key),
            ]);
            return;
        }

        $original = $this->file->getClientOriginalName();
        $path = 'uploads/'.date('Y/m/d/').uniqid().'-'.$original;
        $endpoint = $url.'/storage/v1/object/'.rawurlencode($bucket).'/'.$path;

        try {
            $contents = $this->file->get(); // Stream contents from temporary upload
            $mime = $this->file->getMimeType();
            $resp = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => $mime ?: 'application/octet-stream',
                'x-upsert' => 'true',
            ])->put($endpoint, $contents);

            if ($resp->failed()) {
                $status = $resp->status();
                $body = $resp->json() ?? $resp->body();
                logger()->error('Supabase upload failed', [
                    'status' => $status,
                    'body' => $body,
                ]);
                $this->status = __('Upload failed (:code). :message', [
                    'code' => $status,
                    'message' => is_string($body) ? $body : json_encode($body),
                ]);
                return;
            }

            $publicUrl = rtrim($url, '/').'/storage/v1/object/public/'.rawurlencode($bucket).'/'.$path;
            $this->reset('file');
            $this->status = __('DONE: ').$publicUrl;
            logger()->info('Supabase upload succeeded', [
                'path' => $path,
                'publicUrl' => $publicUrl,
            ]);
        } catch (\Throwable $e) {
            $this->status = __('Error: ').$e->getMessage();
            logger()->error('Supabase upload exception', [
                'exception' => $e,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.admin.files');
    }
}


