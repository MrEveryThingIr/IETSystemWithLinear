<?php

namespace Tests\Feature\Market;

use App\Actions\Market\PublishIntent;
use App\Actions\Market\SaveIntentWorkingCopy;
use App\Models\ActorProfileIntent;
use App\Support\Market\IntentPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ComposerPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_uses_the_same_safe_presentation_as_preview(): void
    {
        $post = ActorProfileIntent::factory()->create(['visibility' => 'public']);
        $owner = $post->profile->actor->user;
        app(SaveIntentWorkingCopy::class)->execute($owner, $post, ['title' => '<script>test</script>', 'description' => 'Public body',
            'schedule_kind' => 'ongoing', 'visibility' => 'public', 'cash_min' => '2100000', 'cash_max' => '2100000', 'currency_code' => 'IRT'],
            [['label' => 'Internal note', 'type' => 'text', 'value' => 'secret-sentinel', 'visibility' => 'private'],
                ['label' => 'Website', 'type' => 'link', 'value' => 'https://example.org/info', 'visibility' => 'same']]);
        $version = app(PublishIntent::class)->execute($owner, $post);
        $data = app(IntentPresentation::class)->build($version->snapshot, null, false, $post);
        $html = Blade::render('<x-market.intention-presentation :presentation="$presentation" />', ['presentation' => $data]);
        $this->get(route('intents.show', $post))->assertOk()->assertSee($html, false)
            ->assertDontSee('secret-sentinel')->assertDontSee('<script>test</script>', false)
            ->assertSee('2,100,000 Toman')->assertSee('example.org');
        $this->assertSame($data, app(IntentPresentation::class)->published($post->fresh(), null));
    }

    public function test_private_draft_and_pending_edits_are_not_exposed_by_public_page(): void
    {
        $post = ActorProfileIntent::factory()->create(['visibility' => 'public']);
        $owner = $post->profile->actor->user;
        app(PublishIntent::class)->execute($owner, $post);
        app(SaveIntentWorkingCopy::class)->execute($owner, $post, ['title' => 'Unpublished sentinel', 'schedule_kind' => 'ongoing', 'visibility' => 'public'], []);
        $this->get(route('intents.show', $post))->assertOk()->assertDontSee('Unpublished sentinel');
        $private = ActorProfileIntent::factory()->create(['status' => 'draft', 'visibility' => 'public']);
        $this->get(route('intents.show', $private))->assertForbidden();
    }

    public function test_unknown_currency_is_hidden_and_private_references_drop_silently(): void
    {
        $data = app(IntentPresentation::class)->build(['core' => ['cash_min' => '50.00', 'currency_code' => 'USD'],
            'details' => [['label' => 'Hidden group', 'type' => 'reference', 'visibility' => 'same', 'payload' => ['value' => ['type' => 'group', 'id' => 999]]],
                ['label' => 'Unsafe link', 'type' => 'link', 'visibility' => 'same', 'payload' => ['value' => 'javascript:alert(1)']]]], null);
        $this->assertNull($data['price']);
        $this->assertSame([], $data['details']);
    }
}
