<style>
    .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px;margin-top:18px}
    .media-card{border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;background:#f8fafc}
    .media-card img,.media-card video{display:block;width:100%;height:220px;object-fit:cover;background:#000}
    .audio-box{padding:18px}.audio-box audio{display:block;width:100%;margin-top:14px}
    .media-meta{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:12px}.media-meta small{display:block;color:#64748b;margin-top:3px}
    .delete-media{border:1px solid #fecdd3;background:#fff1f2;color:#be123c;border-radius:10px;padding:8px 10px;font-weight:900}
    .media-upload{margin-top:20px;padding:18px;border:1px solid #d1fae5;background:#ecfdf5;border-radius:18px}
    .upload-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.upload-grid span{display:block;font-size:13px;font-weight:800;margin-bottom:6px}
    .upload-grid input{width:100%;box-sizing:border-box;border:1.5px solid #94a3b8;background:#fff;border-radius:12px;padding:10px}
    @media(max-width:800px){.upload-grid{grid-template-columns:1fr}}
</style>

<section class="panel" style="margin-top:18px">
    <h2 style="margin:0">گالری و رسانه‌های پرونده</h2>
    <p style="color:#64748b;margin:6px 0 0">تصاویر، ویدئوها و توضیحات صوتی این پرونده</p>

    @if($case->media->isEmpty())
        <div style="margin-top:18px;padding:24px;text-align:center;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;color:#64748b">هنوز رسانه‌ای ثبت نشده است.</div>
    @else
        <div class="media-grid">
            @foreach($case->media as $media)
                <article class="media-card">
                    @if($media->kind === 'image')
                        <img src="{{ route('office.real-estate.media.stream',['portal'=>$portal->uuid,'case'=>$case,'media'=>$media]) }}" alt="{{ $media->original_name }}">
                    @elseif($media->kind === 'video')
                        <video controls preload="metadata" playsinline>
                            <source src="{{ route('office.real-estate.media.stream',['portal'=>$portal->uuid,'case'=>$case,'media'=>$media]) }}" type="{{ $media->mime_type }}">
                        </video>
                    @else
                        <div class="audio-box">
                            <strong>🎙 {{ $media->origin === 'recorded' ? 'صدای ضبط‌شده' : 'فایل صوتی' }}</strong>
                            <audio controls preload="metadata">
                                <source src="{{ route('office.real-estate.media.stream',['portal'=>$portal->uuid,'case'=>$case,'media'=>$media]) }}" type="{{ $media->mime_type }}">
                            </audio>
                        </div>
                    @endif
                    <div class="media-meta">
                        <div><b>{{ $media->original_name ?: $media->kind }}</b><small>{{ number_format($media->size_bytes/1024/1024,2) }} MB</small></div>
                        @if($canManage)
                            <form method="POST" action="{{ route('office.real-estate.media.destroy',['portal'=>$portal->uuid,'case'=>$case,'media'=>$media]) }}" onsubmit="return confirm('این فایل حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="delete-media">حذف</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if($canManage)
        <form method="POST" enctype="multipart/form-data" action="{{ route('office.real-estate.media.store',['portal'=>$portal->uuid,'case'=>$case]) }}" class="media-upload">
            @csrf
            <h3 style="margin-top:0">افزودن رسانه به پرونده</h3>
            <div class="upload-grid">
                <label><span>تصاویر</span><input type="file" name="images[]" accept="image/*" multiple></label>
                <label><span>ویدئو</span><input type="file" name="videos[]" accept="video/*" multiple></label>
                <label><span>صدا</span><input type="file" name="audios[]" accept="audio/*" multiple></label>
            </div>
            <button class="primary" style="margin-top:12px">افزودن فایل‌ها</button>
        </form>
    @endif
</section>
