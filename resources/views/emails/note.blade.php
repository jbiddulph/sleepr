@php /** @var \App\Models\Note $note */ @endphp
<div style="font-family: Arial, sans-serif;">
    <h2>{{ $note->title }}</h2>
    <p>{!! nl2br(e($note->body)) !!}</p>

    @isset($heartUrl)
        <p>
            <a href="{{ $heartUrl }}" style="display:inline-block;padding:10px 16px;background:#e11d48;color:#fff;text-decoration:none;border-radius:6px;">❤️ I love it</a>
        </p>
    @endisset

    <p style="color:#666;font-size:12px;">If you didn’t expect this message, you can ignore it.</p>
</div>


