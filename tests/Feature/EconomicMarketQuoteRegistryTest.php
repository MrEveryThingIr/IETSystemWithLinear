<?php

namespace Tests\Feature;

use App\Actions\Exchange\PublishMarketQuote;
use App\EconomicInstrumentKind;
use App\Models\Actor;
use App\Models\EconomicInstrument;
use App\Models\MarketQuote;
use App\Models\PlatformAccessGrant;
use App\Models\QuoteSource;
use App\PlatformRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EconomicMarketQuoteRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_exchange_manager_can_publish_precise_immutable_quote(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $btc = EconomicInstrument::query()->create([
            'code' => 'btc',
            'name' => 'Bitcoin',
            'kind' => EconomicInstrumentKind::CryptoAsset,
        ]);

        $usd = EconomicInstrument::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'kind' => EconomicInstrumentKind::FiatCurrency,
            'settlement_enabled' => true,
        ]);

        $source = QuoteSource::query()->create([
            'key' => 'manual-admin',
            'name' => 'Manual administrator quote',
            'source_type' => 'manual',
            'trust_tier' => 5,
        ]);

        $quote = app(PublishMarketQuote::class)->execute(
            $admin->user,
            $btc,
            $usd,
            $source,
            '67250.15000000000000000000',
            CarbonImmutable::parse('2026-09-29 06:00:00 UTC'),
            sourceReference: 'BTC-USD-20260929-0600',
            confidenceBps: 9500,
        );

        $this->assertSame('BTC', $btc->fresh()->code);
        $this->assertSame('67250.15000000000000000000', (string) $quote->price);
        $this->assertSame('BTC', $quote->baseInstrument->code);
        $this->assertSame('USD', $quote->quoteInstrument->code);
        $this->assertSame('manual-admin', $quote->source->key);
        $this->assertSame(9500, $quote->confidence_bps);

        $this->expectException(LogicException::class);

        $quote->update(['price' => '68000']);
    }

    public function test_quote_publication_requires_exchange_capability(): void
    {
        $actor = Actor::factory()->create();

        $iet = EconomicInstrument::query()->create([
            'code' => 'IET',
            'name' => 'IET Internal Settlement Unit',
            'kind' => EconomicInstrumentKind::InternalUnit,
            'settlement_enabled' => true,
        ]);

        $usd = EconomicInstrument::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'kind' => EconomicInstrumentKind::FiatCurrency,
        ]);

        $source = QuoteSource::query()->create([
            'key' => 'iet-policy',
            'name' => 'IET policy',
            'source_type' => 'policy',
            'trust_tier' => 10,
        ]);

        try {
            app(PublishMarketQuote::class)->execute(
                $actor->user,
                $iet,
                $usd,
                $source,
                '0.00000001000000000000',
                CarbonImmutable::now(),
            );

            $this->fail('Unauthorized market quote was published.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('market_quotes', 0);
    }

    public function test_quote_requires_distinct_active_instruments_and_positive_exact_decimal(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $gold = EconomicInstrument::query()->create([
            'code' => 'XAU_OZ',
            'name' => 'Gold troy ounce',
            'kind' => EconomicInstrumentKind::Commodity,
        ]);

        $source = QuoteSource::query()->create([
            'key' => 'trusted-gold-reference',
            'name' => 'Trusted gold reference',
            'source_type' => 'provider',
            'trust_tier' => 8,
        ]);

        foreach ([
            [$gold, $gold, '2500.00'],
            [$gold, EconomicInstrument::query()->create([
                'code' => 'USD',
                'name' => 'US Dollar',
                'kind' => EconomicInstrumentKind::FiatCurrency,
                'active' => false,
            ]), '2500.00'],
        ] as [$base, $counter, $price]) {
            try {
                app(PublishMarketQuote::class)->execute(
                    $admin->user,
                    $base,
                    $counter,
                    $source,
                    $price,
                    CarbonImmutable::now(),
                );

                $this->fail('Invalid quote publication was accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }

        $usd = EconomicInstrument::query()->where('code', 'USD')->firstOrFail();
        $usd->forceFill(['active' => true])->save();

        try {
            app(PublishMarketQuote::class)->execute(
                $admin->user,
                $gold,
                $usd,
                $source,
                '0',
                CarbonImmutable::now(),
            );

            $this->fail('Zero-valued quote was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('market_quotes', 0);
    }

    public function test_market_quote_rows_cannot_be_deleted(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $service = EconomicInstrument::query()->create([
            'code' => 'SERVICE_QC_PART',
            'name' => 'Quality-control inspection service unit',
            'kind' => EconomicInstrumentKind::ServiceUnit,
        ]);

        $usd = EconomicInstrument::query()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'kind' => EconomicInstrumentKind::FiatCurrency,
        ]);

        $source = QuoteSource::query()->create([
            'key' => 'service-offer-reference',
            'name' => 'Service offer reference',
        ]);

        $quote = app(PublishMarketQuote::class)->execute(
            $admin->user,
            $service,
            $usd,
            $source,
            '18.00',
            CarbonImmutable::now(),
        );

        $this->assertInstanceOf(MarketQuote::class, $quote);

        $this->expectException(LogicException::class);

        $quote->delete();
    }
}
