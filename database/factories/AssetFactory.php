<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\GroupSpace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'group_space_id' => GroupSpace::factory()->restricted(),
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'byte_size' => 1024,
            'disk' => 'local',
            'storage_key' => 'assets/testing/'.$uuid.'.pdf',
            'sha256' => hash('sha256', $uuid),
            'uploaded_by_actor_id' => Actor::factory(),
            'scan_status' => 'unavailable',
            'scan_error' => null,
            'scan_attempted_at' => null,
            'scan_completed_at' => null,
            'processing_status' => 'ready',
            'processing_error' => null,
            'processing_completed_at' => now(),
            'readiness_verified_at' => now(),
            'rights_status' => 'owned',
            'metadata' => null,
        ];
    }
}
