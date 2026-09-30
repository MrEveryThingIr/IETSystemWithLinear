<?php

namespace App\Support;

use App\Models\PublicRealEstateCase;
use App\Models\PublicRealEstateCaseMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicRealEstateCaseMediaStore
{
    public function storeUploaded(
        PublicRealEstateCase $case,
        UploadedFile $file,
        string $kind,
        string $origin = 'upload'
    ): PublicRealEstateCaseMedia {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $directory = 'real-estate-cases/'.$case->getKey().'/'.$kind;
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs($directory, $filename, 'local');

        return $case->media()->create([
            'kind' => $kind,
            'origin' => $origin,
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'sort_order' => ($case->media()->where('kind', $kind)->max('sort_order') ?? -1) + 1,
        ]);
    }

    public function delete(PublicRealEstateCaseMedia $media): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}
