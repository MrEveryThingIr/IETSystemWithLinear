<?php

namespace App\Providers;

use App\Contracts\Media\VideoProcessor;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Bookkeeping\Dispute;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\CommitmentEvent;
use App\Models\ContractEvent;
use App\Models\ConversationMessage;
use App\Models\Evaluation;
use App\Models\ProposalEvent;
use App\Models\RelationshipEvent;
use App\Models\Submission;
use App\Policies\Bookkeeping\LedgerPolicy;
use App\Policies\Bookkeeping\TransactionPolicy;
use App\Support\DomainNotificationProjector;
use App\Support\Media\LocalVideoProcessor;
use App\Support\MediaScannerPolicy;
use App\Support\ReleaseManifest;
use App\Support\RequestMemo;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoProcessor::class, fn () => match ((string) config('media.video.processor', 'local')) {
            default => new LocalVideoProcessor,
        });
    }

    public function boot(): void
    {
        // Per-request memo: start empty for every request and forget it on
        // any database write, so a remembered permission is never stale.
        Event::listen(RouteMatched::class, fn () => RequestMemo::flush());
        DB::listen(function (QueryExecuted $query): void {
            if (! preg_match('/^\s*(select|pragma|show)\b/i', $query->sql)) {
                RequestMemo::flush();
            }
        });
        foreach (RequestMemo::invalidatingModels() as $model) {
            $model::saved(fn () => RequestMemo::flush());
            $model::deleted(fn () => RequestMemo::flush());
        }

        Gate::policy(Ledger::class, LedgerPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Dispute::class, TransactionPolicy::class);

        Log::shareContext([
            'app_version' => (string) config('app.version', 'unknown'),
        ]);

        // `php artisan about --only=release` proves which GitHub build is running.
        AboutCommand::add('Release', fn (): array => ReleaseManifest::aboutRows());
        AboutCommand::add('Video', fn (): array => [
            'Processor' => app(VideoProcessor::class)->name(),
            'Resumable chunk (KB)' => (string) config('media.resumable.chunk_kb'),
            'Max video (MB)' => (string) intdiv((int) config('media.resumable.max_video_kb'), 1024),
        ]);
        AboutCommand::add('Media scanner', function (): array {
            $policy = app(MediaScannerPolicy::class);

            return [
                'Enabled' => $policy->enabled() ? 'yes' : 'no',
                'Required' => $policy->required() ? 'yes' : 'no',
                'Process execution (proc_open)' => $policy->enabled()
                    ? ($policy->processExecutionAvailable() ? 'available' : 'unavailable')
                    : 'not needed',
            ];
        });

        RateLimiter::for('invitation-acceptance', function (Request $request): Limit {
            $identity = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return Limit::perMinute(10)->by('invitation-acceptance:'.$identity);
        });

        Livewire::addPersistentMiddleware([
            RedirectIfAuthenticated::class,
            EnsureEmailIsVerified::class,
            EnsureAccountIsActive::class,
        ]);

        RelationshipEvent::created(
            fn (RelationshipEvent $event) => app(DomainNotificationProjector::class)->relationship($event),
        );
        ProposalEvent::created(
            fn (ProposalEvent $event) => app(DomainNotificationProjector::class)->proposal($event),
        );
        ContractEvent::created(
            fn (ContractEvent $event) => app(DomainNotificationProjector::class)->contract($event),
        );
        CommitmentEvent::created(
            fn (CommitmentEvent $event) => app(DomainNotificationProjector::class)->commitment($event),
        );
        ConversationMessage::created(
            fn (ConversationMessage $message) => app(DomainNotificationProjector::class)->conversation($message),
        );
        Submission::updated(function (Submission $submission): void {
            if ($submission->wasChanged('status') && $submission->status === Submission::STATUS_SUBMITTED) {
                app(DomainNotificationProjector::class)->submission($submission);
            }
        });
        Evaluation::updated(function (Evaluation $evaluation): void {
            if ($evaluation->wasChanged('status') && $evaluation->status === Evaluation::STATUS_FINALIZED) {
                app(DomainNotificationProjector::class)->evaluation($evaluation);
            }
        });
    }
}
