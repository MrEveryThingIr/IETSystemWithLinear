<?php

namespace App\Console\Commands;

use App\Models\DocInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('docs:invite {label? : Who or what the link is for} {--days=30 : Days until it expires (0 = never)} {--uses= : Maximum number of openings} {--revoke= : Revoke the invitation with this id instead}')]
#[Description('Create (or revoke) an invitation link for the documentation while it is in invite-only mode')]
class DocsInviteCommand extends Command
{
    public function handle(): int
    {
        if ($this->option('revoke') !== null) {
            $invitation = DocInvitation::query()->find((int) $this->option('revoke'));

            if (! $invitation instanceof DocInvitation) {
                $this->error('No such documentation invitation.');

                return self::FAILURE;
            }

            $invitation->forceFill(['revoked_at' => now()])->save();
            $this->info("Invitation #{$invitation->id} revoked.");

            return self::SUCCESS;
        }

        $label = trim((string) $this->argument('label'));

        if ($label === '') {
            $this->table(
                ['id', 'label', 'uses', 'max', 'expires', 'revoked'],
                DocInvitation::query()->orderBy('id')->get()->map(fn (DocInvitation $i): array => [
                    $i->id, $i->label, $i->uses_count, $i->max_uses ?? '-', $i->expires_at?->toDateString() ?? 'never', $i->revoked_at?->toDateString() ?? '-',
                ])->all(),
            );

            return self::SUCCESS;
        }

        $token = Str::random(48);
        $days = (int) $this->option('days');
        $invitation = DocInvitation::query()->create([
            'label' => $label,
            'token_hash' => DocInvitation::hashToken($token),
            'expires_at' => $days > 0 ? now()->addDays($days) : null,
            'max_uses' => $this->option('uses') !== null ? (int) $this->option('uses') : null,
        ]);

        $this->info("Invitation #{$invitation->id} created. The link is shown once:");
        $this->line(route('docs.join', $token));

        return self::SUCCESS;
    }
}
