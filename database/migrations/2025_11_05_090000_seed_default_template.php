<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('templates')->where('slug', 'default')->first();
        if ($exists) {
            return;
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{title}}</title>
  <style>
    body { background:#f6f6f6; margin:0; padding:0; }
    .container { width:100%; background:#f6f6f6; padding:20px 0; }
    .content { width:100%; max-width:600px; margin:0 auto; background:#ffffff; border-radius:4px; overflow:hidden; }
    .header { background:#0ea5e9; color:#fff; padding:16px 24px; font-family:ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu; }
    .body { padding:24px; color:#111827; font-family:ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu; line-height:1.6; }
    .btn { display:inline-block; background:#ef4444; color:#fff; padding:10px 16px; border-radius:6px; text-decoration:none; }
    .footer { padding:16px 24px; font-size:12px; color:#6b7280; font-family:ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu; }
  </style>
</head>
<body>
  <div class="container">
    <div class="content">
      <div class="header">{{title}}</div>
      <div class="body">
        <div>{{body}}</div>
        <p style="margin-top:20px">
          <a class="btn" href="{{heart_url}}">❤ Tap to send a heart</a>
        </p>
      </div>
      <div class="footer">You received this note via Sleepr.</div>
    </div>
  </div>
</body>
</html>
HTML;

        DB::table('templates')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Default',
            'slug' => 'default',
            'html' => $html,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('templates')->where('slug', 'default')->delete();
    }
};


