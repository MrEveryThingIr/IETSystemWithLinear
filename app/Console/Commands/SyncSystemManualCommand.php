<?php

namespace App\Console\Commands;

use App\Actions\Content\EnsureSystemManualContent;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('system-manual:sync {owner : Email of the Actor who manages the official manual}')]
#[Description('Synchronize repository-owned IET manual source into new official Content revisions')]
class SyncSystemManualCommand extends Command
{
    public function handle(EnsureSystemManualContent $manual): int
    {
        $email = mb_strtolower(trim((string) $this->argument('owner')));

        $owner = User::query()
            ->with('actor')
            ->where('email', $email)
            ->first();

        if (! $owner instanceof User) {
            $this->error('The requested manual owner account does not exist.');

            return self::FAILURE;
        }

        try {
            $result = $manual->execute($owner, syncSource: true);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $revision = $result['root']->activeRevisionRecord();

        $this->info('IET System Manual source synchronized as versioned Content.');
        $this->line('Reader: '.route('contexts.contents.show', [
            $result['context'],
            $result['root'],
            'manual' => 1,
        ]));
        $this->line('Root edition: '.$revision->revision);

        return self::SUCCESS;
    }
}
