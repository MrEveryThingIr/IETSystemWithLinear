<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\CommitmentEvent;
use App\Models\ContractEvent;
use App\Models\ConversationMessage;
use App\Models\Evaluation;
use App\Models\FinancialObligationEvent;
use App\Models\ProposalEvent;
use App\Models\RelationshipEvent;
use App\Models\Submission;
use App\Support\DomainNotificationProjector;
use App\Support\MediaScannerPolicy;
use App\Support\ReleaseManifest;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Log::shareContext([
            'app_version' => (string) config('app.version', 'unknown'),
        ]);

        // `php artisan about --only=release` proves which GitHub build is running.
        AboutCommand::add('Release', fn (): array => ReleaseManifest::aboutRows());
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
        FinancialObligationEvent::created(
            fn (FinancialObligationEvent $event) => app(DomainNotificationProjector::class)->financial($event),
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
