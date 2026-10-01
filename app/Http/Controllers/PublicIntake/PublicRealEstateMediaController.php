<?php

namespace App\Http\Controllers\PublicIntake;

use App\Http\Controllers\Controller;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use App\Models\PublicRealEstateCaseMedia;
use App\Support\PublicIntakeAccess;
use App\Support\PublicRealEstateCaseMediaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicRealEstateMediaController extends Controller
{
    public function store(
        Request $request,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case,
        PublicRealEstateCaseMediaStore $mediaStore
    ): RedirectResponse {
        abort_unless(PublicIntakeAccess::canManage($request->user(), $portal), 403);
        abort_unless($case->public_intake_portal_id === $portal->getKey(), 404);

        $request->validate([
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:12288'],
            'videos' => ['nullable', 'array', 'max:6'],
            'videos.*' => ['file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:102400'],
            'audios' => ['nullable', 'array', 'max:6'],
            'audios.*' => ['file', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,video/webm', 'max:25600'],
        ]);

        foreach ($request->file('images', []) as $file) {
            $mediaStore->storeUploaded($case, $file, 'image');
        }
        foreach ($request->file('videos', []) as $file) {
            $mediaStore->storeUploaded($case, $file, 'video');
        }
        foreach ($request->file('audios', []) as $file) {
            $mediaStore->storeUploaded($case, $file, 'audio');
        }

        return back()->with('status', 'رسانه‌های جدید به پرونده اضافه شدند.');
    }

    public function stream(
        Request $request,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case,
        PublicRealEstateCaseMedia $media
    ): BinaryFileResponse {
        abort_unless(PublicIntakeAccess::canView($request->user(), $portal), 403);
        abort_unless($case->public_intake_portal_id === $portal->getKey(), 404);
        abort_unless($media->public_real_estate_case_id === $case->getKey(), 404);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return response()->file(Storage::disk($media->disk)->path($media->path), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(
        Request $request,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case,
        PublicRealEstateCaseMedia $media,
        PublicRealEstateCaseMediaStore $mediaStore
    ): RedirectResponse {
        abort_unless(PublicIntakeAccess::canManage($request->user(), $portal), 403);
        abort_unless($case->public_intake_portal_id === $portal->getKey(), 404);
        abort_unless($media->public_real_estate_case_id === $case->getKey(), 404);

        $mediaStore->delete($media);

        return back()->with('status', 'رسانه حذف شد.');
    }
}
