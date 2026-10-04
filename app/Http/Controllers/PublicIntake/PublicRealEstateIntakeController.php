<?php

namespace App\Http\Controllers\PublicIntake;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicIntake\StorePublicRealEstateIntakeRequest;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use App\Services\Contacts\BusinessContactResolver;
use App\Support\PublicRealEstateCaseMediaStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicRealEstateIntakeController extends Controller
{
    public function show(PublicIntakePortal $portal): SymfonyResponse
    {
        $portal->loadMissing('business');
        $this->ensureAvailable($portal);

        return $this->privateView('public-intake.real-estate.show', [
            'portal' => $portal,
            'business' => $portal->business,
        ]);
    }

    public function store(
        StorePublicRealEstateIntakeRequest $request,
        PublicIntakePortal $portal,
        PublicRealEstateCaseMediaStore $mediaStore,
        BusinessContactResolver $contactResolver
    ): SymfonyResponse {
        $this->ensureAvailable($portal);
        $portal->loadMissing('business');

        $data = $request->validated();
        unset(
            $data['website'],
            $data['images'],
            $data['videos'],
            $data['audios'],
            $data['recorded_audio'],
            $data['recorded_video']
        );

        $businessContact = $contactResolver->resolve(
            $portal->business ?? $portal,
            (string) $request->input('contact_name'),
            (string) $request->input('phone'),
            'real_estate_public_intake'
        );

        $previewToken = Str::random(64);

        $case = DB::transaction(function () use ($request, $portal, $data, $previewToken, $businessContact): PublicRealEstateCase {
            $case = $portal->realEstateCases()->make();
            $case->fill([
                ...$data,
                'business_contact_id' => $businessContact->getKey(),
                'reference_code' => $this->makeReferenceCode(),
                'built_year_calendar' => $data['built_year_calendar'] ?? 'jalali',
                'price_unit' => $data['price_unit'] ?? 'toman',
                'status' => 'new',
                'ip_hash' => $request->ip()
                    ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                    : null,
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'preview_token_hash' => hash('sha256', $previewToken),
                'preview_expires_at' => now()->addMinutes(15),
            ]);
            $case->save();

            return $case;
        });

        try {
            foreach ($request->file('images', []) as $file) {
                $mediaStore->storeUploaded($case, $file, 'image');
            }
            foreach ($request->file('videos', []) as $file) {
                $mediaStore->storeUploaded($case, $file, 'video');
            }
            foreach ($request->file('audios', []) as $file) {
                $mediaStore->storeUploaded($case, $file, 'audio');
            }
            if ($request->hasFile('recorded_audio')) {
                $mediaStore->storeUploaded($case, $request->file('recorded_audio'), 'audio', 'recorded');
            }
            if ($request->hasFile('recorded_video')) {
                $mediaStore->storeUploaded($case, $request->file('recorded_video'), 'video', 'recorded');
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('public.real-estate.preview', [
                'case' => $case,
                'token' => $previewToken,
            ])->with('media_warning', __('public_real_estate.media.upload_warning'));
        }

        return redirect()->route('public.real-estate.preview', [
            'case' => $case,
            'token' => $previewToken,
        ]);
    }

    public function preview(Request $request, PublicRealEstateCase $case): SymfonyResponse
    {
        $token = (string) $request->query('token');
        if ($token === '') {
            abort(404);
        }

        $case = DB::transaction(function () use ($case, $token): PublicRealEstateCase {
            $locked = PublicRealEstateCase::query()->whereKey($case->getKey())->lockForUpdate()->first();

            if (
                ! $locked
                || $locked->preview_viewed_at
                || ! $locked->preview_expires_at
                || $locked->preview_expires_at->isPast()
                || ! hash_equals($locked->preview_token_hash, hash('sha256', $token))
            ) {
                abort(404);
            }

            $locked->forceFill(['preview_viewed_at' => now()])->save();
            $locked->load(['portal', 'media']);

            return $locked;
        });

        return $this->privateView('public-intake.real-estate.preview', [
            'case' => $case,
            'portal' => $case->portal,
        ]);
    }

    private function ensureAvailable(PublicIntakePortal $portal): void
    {
        $portal->loadMissing('business');

        abort_unless($portal->is_active && $portal->type === 'real_estate', 404);

        if ($portal->business !== null) {
            abort_unless(
                $portal->business->status === 'active'
                && $portal->business->visibility === 'public',
                404,
            );
        }
    }

    private function makeReferenceCode(): string
    {
        do {
            $code = 'RE-'.Str::upper(Str::random(10));
        } while (PublicRealEstateCase::query()->where('reference_code', $code)->exists());

        return $code;
    }

    private function privateView(string $view, array $data): SymfonyResponse
    {
        return response()->view($view, $data)
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY');
    }
}
