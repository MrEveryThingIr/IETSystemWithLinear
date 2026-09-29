<?php

namespace Tests\Feature;

use App\Actions\Exchange\PublishIetValuationQuote;
use App\Actions\Exchange\PublishManualMarketQuote;
use App\Actions\Exchange\PublishMarketQuote;
use App\Actions\Exchange\RegisterEconomicInstrument;
use App\EconomicInstrumentKind;
use App\Models\Actor;
use App\Models\EconomicInstrument;
use App\Models\IetValuationQuote;
use App\Models\MarketQuote;
use App\Models\PlatformAccessGrant;
use App\Models\QuoteSource;
use App\PlatformRole;
use App\Support\IetPricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EconomicMarketQuoteRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_manager_can_register_service_unit_and_publish_manual_reference_quote(): void
    {
        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        app(IetPricing::class)->currentQuote();

        $service = app(RegisterEconomicInstrument::class)->execute(
            $admin->user,
            'service_qc_part',
            'Inspect one machined part',
            EconomicInstrumentKind::ServiceUnit,
        );

        $usd = EconomicInstrument::query()->where('code', 'USD')->firstOrFail();

        $quote = app(PublishManualMarketQuote::class)->execute(
            $admin->user,
            $service->uuid,
            $usd->uuid,
            '18.50',
            'Provider offer backed by the current service-unit definition.',
            'OFFER-QC-001',
        );

        $this->assertSame('SERVICE_QC_PART', $service->code);
        $this->assertSame(EconomicInstrumentKind::ServiceUnit, $service->kind);
        $this->assertFalse($service->settlement_enabled);
        $this->assertSame('18.50000000000000000000', (string) $quote->price);
        $this->assertSame('manual-admin', $quote->source->key);
        $this->assertSame('OFFER-QC-001', $quote->source_reference);
        $this->assertSame(
            'Provider offer backed by the current service-unit definition.',
            $quote->metadata['rationale'],
        );
    }

    public function test_current_and_published_iet_valuations_are_mirrored_into_general_market_history(): void
    {
        $initial = app(IetPricing::class)->currentQuote();

        $this->assertDatabaseHas('market_quotes', [
            'source_reference' => 'iet-valuation:'.$initial->uuid,
        ]);

        $admin = Actor::factory()->create();

        PlatformAccessGrant::factory()->create([
            'user_id' => $admin->user->id,
            'role' => PlatformRole::Superadmin,
        ]);

        $published = app(PublishIetValuationQuote::class)->execute(
            $admin->user,
            '0.000000010100000000',
            'Audited compatibility bridge proof.',
            ['successful_flow_count' => 12],
        );

        $this->assertInstanceOf(IetValuationQuote::class, $published);
        $this->assertDatabaseHas('market_quotes', [
            'source_reference' => 'iet-valuation:'.$published->uuid,
            'published_by_user_id' => $admin->user->id,
        ]);

        $marketQuote = MarketQuote::query()
            ->where('source_reference', 'iet-valuation:'.$published->uuid)
            ->with(['baseInstrument', 'quoteInstrument', 'source'])
            ->sole();

        $this->assertSame('IET', $marketQuote->baseInstrument->code);
        $this->assertSame('USD', $marketQuote->quoteInstrument->code);
        $this->assertSame('iet-valuation-policy', $marketQuote->source->key);
        $this->assertSame('0.00000001010000000000', (string) $marketQuote->price);
        $this->assertDatabaseCount('market_quotes', 2);

        app(IetPricing::class)->currentQuote();

        $this->assertDatabaseCount('market_quotes', 2);
    }

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
