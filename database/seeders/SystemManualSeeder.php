<?php

namespace Database\Seeders;

use App\Actions\Content\EnsureSystemManualContent;
use App\Models\User;
use Illuminate\Database\Seeder;

class SystemManualSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $owner = User::query()->where('email', 'test@example.com')->first()
            ?? User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $owner->actor()->firstOrCreate([]);

        $manual = app(EnsureSystemManualContent::class)->execute($owner->refresh());

        $this->command?->info('IET System Manual materialized as normal versioned Content.');
        $this->command?->line('Manual library: '.route('contexts.contents.index', $manual['context']));
        $this->command?->line('Manual reader: '.route('contexts.contents.show', [$manual['context'], $manual['root']]));
        $this->command?->line('All active verified users may read/annotate; test@example.com manages the official editions.');
    }
}
