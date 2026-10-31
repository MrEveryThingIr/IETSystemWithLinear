<?php

namespace App\Console\Commands;

use App\Models\DocContribution;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('docs:contributions {--status=new : new, reviewed, accepted or declined} {--show= : Show one contribution in full by id} {--mark= : Id of a contribution to update} {--as= : New status for --mark}')]
#[Description('List proposals sent from the documentation, read one in full, or record how it was handled')]
class DocsContributionsCommand extends Command
{
    public function handle(): int
    {
        if ($this->option('mark') !== null) {
            $status = (string) $this->option('as');
            $contribution = DocContribution::query()->find((int) $this->option('mark'));

            if (! $contribution instanceof DocContribution || ! in_array($status, DocContribution::STATUSES, true)) {
                $this->error('Give an existing id and --as=new|reviewed|accepted|declined.');

                return self::FAILURE;
            }

            $contribution->forceFill(['status' => $status])->save();
            $this->info("Contribution #{$contribution->id} is now {$status}.");

            return self::SUCCESS;
        }

        if ($this->option('show') !== null) {
            $c = DocContribution::query()->find((int) $this->option('show'));

            if (! $c instanceof DocContribution) {
                $this->error('No such contribution.');

                return self::FAILURE;
            }

            foreach (['topic', 'name', 'email', 'field', 'expertise', 'education', 'experience', 'links', 'proposal', 'locale', 'status', 'may_credit', 'created_at'] as $key) {
                $this->line(str_pad($key, 11).': '.($c->{$key} instanceof CarbonInterface ? $c->{$key}->toDateTimeString() : var_export($c->{$key}, true)));
            }

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'when', 'topic', 'field', 'name', 'status'],
            DocContribution::query()->where('status', (string) $this->option('status'))->orderBy('id')->get()->map(fn (DocContribution $c): array => [
                $c->id, $c->created_at?->toDateString(), $c->topic, $c->field, $c->name, $c->status,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
