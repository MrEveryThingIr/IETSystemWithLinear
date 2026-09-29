<?php

namespace Tests\Feature;

use App\Actions\Exchange\CreateIetExchangeRequest;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\IetExchangeDirection;
use App\Models\Actor;
use App\Models\PlatformAccessGrant;
use App\Models\SystemContext;
use App\PlatformRole;
use App\Support\AccountingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IetTreasuryAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_exchange_funding_and_cashout_reconcile_global_reserve_and_net_issuance(): void
    {
        $owner = Actor::factory()->create();
        $reviewer = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $reviewer->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $deposit = app(CreateIetExchangeRequest::class)->execute(
            $owner->user,
            IetExchangeDirection::Deposit,
            350,
            'EXT-DEPOSIT-350',
        );

        app(ReviewIetExchangeRequest::class)->confirm($deposit, $reviewer->user);

        $treasury = SystemContext::query()
            ->with('context.ledgers.accounts')
            ->where('key', 'iet-treasury')
            ->sole();

        $ledger = $treasury->context->ledgers()->where('key', 'treasury')->sole();
        $reserve = $ledger->accounts()->where('system_key', 'iet_exchange_reserve')->sole();
        $issuance = $ledger->accounts()->where('system_key', 'iet_issuance')->sole();
        $summary = app(AccountingSummary::class);

        $this->assertSame(350_000_000, $summary->accountBalanceMinor($reserve));
        $this->assertSame(350_000_000, $summary->accountBalanceMinor($issuance));

        $cashout = app(CreateIetExchangeRequest::class)->execute(
            $owner->user,
            IetExchangeDirection::Cashout,
            100,
            'EXT-CASHOUT-100',
        );

        app(ReviewIetExchangeRequest::class)->confirm($cashout, $reviewer->user);

        $this->assertSame(250_000_000, $summary->accountBalanceMinor($reserve));
        $this->assertSame(250_000_000, $summary->accountBalanceMinor($issuance));
        $this->assertSame(
            2,
            $ledger->journalEntries()->where('source_type', 'iet_exchange_request')->count(),
        );
    }

    public function test_exchange_request_cannot_be_self_reviewed_and_does_not_create_treasury_posting(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $request = app(CreateIetExchangeRequest::class)->execute(
            $admin->user,
            IetExchangeDirection::Deposit,
            100,
        );

        try {
            app(ReviewIetExchangeRequest::class)->confirm($request, $admin->user);

            $this->fail('An exchange request was self-reviewed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('system_contexts', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
