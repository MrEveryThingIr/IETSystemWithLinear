<div style="position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid #e5e7eb">
    <div style="max-width:1220px;margin:auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <a href="{{ route('workspace.index') }}" style="text-decoration:none;background:#111827;color:#fff;border-radius:12px;padding:9px 12px;font-weight:900">⌂ فضای من</a>
            <a href="{{ $workspaceBack ?? route('workspace.index') }}" style="text-decoration:none;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;border-radius:12px;padding:9px 12px;font-weight:900">→ بازگشت</a>
        </div>
        <div style="font-weight:950;color:#344054">{{ $workspaceTitle ?? 'IET' }}</div>
    </div>
</div>
