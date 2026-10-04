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

        $username = (string) config('bootstrap.superadmin.username', 'MrEveryThing');

        $owner = User::query()->where('username', $username)->first();

        if (! $owner instanceof User) {
            $this->call(BootstrapSuperadminSeeder::class);
            $owner = User::query()->where('username', $username)->firstOrFail();
        }

        $owner->actor()->firstOrCreate([]);

        $manual = app(EnsureSystemManualContent::class)->execute($owner->refresh());

        $this->command?->info('IET System Manual materialized as normal versioned Content.');
        $this->command?->line('Manual library: '.route('contexts.contents.index', $manual['context']));
        $this->command?->line('Manual reader: '.route('contexts.contents.show', [$manual['context'], $manual['root']]));
        $this->command?->line('All active verified users may read/annotate; '.$owner->email.' manages the official editions.');
    }
}
