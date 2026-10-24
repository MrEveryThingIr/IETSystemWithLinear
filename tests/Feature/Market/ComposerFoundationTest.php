<?php

namespace Tests\Feature\Market;

use App\Actions\Market\BackfillIntentVersions;
use App\Actions\Market\PublishIntent;
use App\Actions\Market\SaveIntentWorkingCopy;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Actions\Profile\UpdateActorProfileIntent;
use App\Actions\Relationships\CreateRelationship;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\Support\Market\MarketMoney;
use App\Support\Market\NormalizeIntentDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ComposerFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        $user = Actor::factory()->create()->user;
        $profile = app(EnsureActorProfile::class)->execute($user);
        $post = app(CreateActorProfileIntent::class)->execute($user, $profile, ProfileIntentKind::Need, 'Laptop repair',
            ['title' => 'Repair my laptop', 'description' => 'It will not start.', 'schedule_kind' => 'ongoing', 'visibility' => 'public'], draft: true);

        return [$user, $post];
    }

    public function test_draft_is_private_and_cannot_be_published_by_the_legacy_status_switch(): void
    {
        [$user, $post] = $this->draft();
        $this->assertSame(ProfileIntentStatus::Draft, $post->status);
        $this->assertFalse(ActorProfileIntent::query()->whereKey($post->id)->visibleTo(Actor::factory()->create()->user)->exists());
        $this->expectException(HttpException::class);
        app(SetActorProfileIntentStatus::class)->execute($user, $post, ProfileIntentStatus::Active);
    }

    public function test_pending_core_and_private_details_do_not_change_the_published_record_or_index(): void
    {
        [$user, $post] = $this->draft();
        $core = ['title' => 'Original', 'description' => 'Public description', 'schedule_kind' => 'ongoing', 'visibility' => 'public'];
        $details = [['label' => 'Public note', 'type' => 'text', 'value' => 'visible text', 'visibility' => 'same'],
            ['label' => 'Secret note', 'type' => 'text', 'value' => 'private-sentinel', 'visibility' => 'private'],
            ['canonical_key' => 'rooms', 'type' => 'number', 'value' => '۲', 'visibility' => 'same']];
        app(SaveIntentWorkingCopy::class)->execute($user, $post, $core, $details);
        $first = app(PublishIntent::class)->execute($user, $post);
        $this->assertSame(1, $first->version);
        $this->assertStringNotContainsString('private-sentinel', $first->search_text);
        $this->assertSame(1, DB::table('intent_search_values')->where('intent_version_id', $first->id)->count());
        app(SaveIntentWorkingCopy::class)->execute($user, $post, $core, $details);
        $this->assertSame($first->id, app(PublishIntent::class)->execute($user, $post)->id);
        app(SaveIntentWorkingCopy::class)->execute($user, $post, [...$core, 'title' => 'Pending title'], $details);
        $this->assertSame('Original', $post->fresh()->title);
        $this->assertSame('Original', $first->fresh()->snapshot['core']['title']);
        app(PublishIntent::class)->discard($user, $post);
        $this->assertNull($post->fresh()->working_copy);
        $this->assertSame($first->id, app(PublishIntent::class)->execute($user, $post)->id);
    }

    public function test_versions_are_immutable_and_republication_keeps_the_old_snapshot(): void
    {
        [$user, $post] = $this->draft();
        $first = app(PublishIntent::class)->execute($user, $post);
        app(SaveIntentWorkingCopy::class)->execute($user, $post, ['title' => 'New title', 'schedule_kind' => 'ongoing', 'visibility' => 'public'], []);
        $second = app(PublishIntent::class)->execute($user, $post);
        $this->assertSame(2, $second->version);
        $this->assertSame('Repair my laptop', $first->fresh()->snapshot['core']['title']);
        $this->expectException(LogicException::class);
        $first->update(['search_text' => 'Tampered']);
    }

    public function test_reserved_labels_and_unknown_canonical_keys_are_rejected(): void
    {
        [$user] = $this->draft();
        foreach ([['label' => 'قیمت'], ['canonical_key' => 'rooms); DROP TABLE users;--', 'label' => 'Unsafe']] as $bad) {
            try {
                app(NormalizeIntentDetails::class)->execute($user, [[...$bad, 'type' => 'text', 'value' => 'x', 'visibility' => 'same']]);
                $this->fail('Unsafe detail was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_hostile_descriptive_text_is_not_interpreted_as_a_filter_key(): void
    {
        [$user] = $this->draft();
        $rows = app(NormalizeIntentDetails::class)->execute($user, [['label' => '<script>alert(1)</script>', 'type' => 'text', 'value' => "'; DROP TABLE users;--", 'visibility' => 'same']]);
        $this->assertNull($rows[0]['canonical_key']);
        $this->assertSame("'; DROP TABLE users;--", $rows[0]['payload']['value']);
    }

    public function test_backfill_is_idempotent_and_preserves_legacy_currency_and_deal_source(): void
    {
        $legacy = ActorProfileIntent::factory()->create(['cash_min' => '50.00', 'currency_code' => 'USD']);
        $before = $legacy->getRawOriginal();
        $this->assertSame(1, app(BackfillIntentVersions::class)->execute());
        $this->assertSame(0, app(BackfillIntentVersions::class)->execute());
        $this->assertSame('USD', $legacy->fresh()->currency_code);
        $this->assertSame('50.00', $legacy->fresh()->cash_min);
        $this->assertSame($before['created_at'], $legacy->fresh()->getRawOriginal('created_at'));
        $this->assertSame(1, IntentVersion::query()->where('actor_profile_intent_id', $legacy->id)->count());
    }

    public function test_market_money_uses_whole_toman_without_rewriting_unknown_currencies(): void
    {
        $this->assertSame(2100000, MarketMoney::parse('۲٬۱۰۰٬۰۰۰'));
        $this->assertSame('123.45', MarketMoney::legacyToman('1234.50', 'IRR'));
        $this->assertNull(MarketMoney::legacyToman('50.00', 'USD'));
    }

    public function test_legacy_editor_cannot_accidentally_publish_pending_composer_changes(): void
    {
        [$user, $post] = $this->draft();
        app(PublishIntent::class)->execute($user, $post);
        $post->refresh();
        app(SaveIntentWorkingCopy::class)->execute($user, $post, ['title' => 'Unpublished secret', 'schedule_kind' => 'ongoing'], []);
        try {
            app(UpdateActorProfileIntent::class)->execute($user, $post, ['title' => 'Legacy edit', 'schedule_kind' => 'ongoing']);
            $this->fail('Legacy update published pending work.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('Repair my laptop', $post->fresh()->title);
        $this->assertSame('Unpublished secret', $post->fresh()->working_copy['core']['title']);
    }

    public function test_new_deal_pins_the_source_version_even_after_republication(): void
    {
        [$user, $post] = $this->draft();
        $first = app(PublishIntent::class)->execute($user, $post);
        $post->refresh();
        $provider = Actor::factory()->create();
        $deal = app(CreateRelationship::class)->execute($provider->user, $post->concept, 'provider',
            [['actor' => $user->actor, 'role' => 'client']], $post);
        $this->assertSame($first->id, $deal->originating_intent_version_id);
        app(SaveIntentWorkingCopy::class)->execute($user, $post, ['title' => 'New terms', 'schedule_kind' => 'ongoing', 'visibility' => 'public'], []);
        $second = app(PublishIntent::class)->execute($user, $post);
        $this->assertNotSame($second->id, $deal->fresh()->originating_intent_version_id);
        $this->assertSame($first->id, $deal->fresh()->originating_intent_version_id);
    }
}
