<?php

namespace Tests\Feature;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Livewire\Contexts\ContentShow;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\GroupInvitation;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentBlock;
use App\Support\Content\SectionSemantics;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Asset = the uploaded file. Placement = this occurrence of it in this Content (one block). Response = an ordinary
 * annotation anchored to that block. A caption is the author's words about the item; an answer is a reader's.
 */
class PlacementMediaActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: Actor, 1: GroupSpace, 2: SpaceContent} the owner uploads six chess diagrams together */
    private function worksheet(bool $answerable = true): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Chess club', null);
        $space = GroupSpace::query()->where('group_id', $group->id)->where('is_default', true)->sole();
        $definition = app(CreateSpaceContentDefinition::class)->execute($space, $owner->user, 'Worksheet', null, [[
            'key' => 'body', 'label' => 'Body', 'type' => 'long_text', 'required' => false, 'help' => null, 'options' => [],
        ]]);
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);
        $content = app(CreateSpaceContent::class)->execute($space, $definition, $owner->user, 'Page 7', ['body' => '']);

        $studio = Livewire::actingAs($owner->user)->test(ContentStudio::class, ['context' => $content->context, 'content' => $content])
            ->call('openMediaDialog', 'image')
            ->set('assetUploads', array_map(fn (int $n) => UploadedFile::fake()->image("position-$n.png", 200 + $n, 200), range(1, 6)))
            ->set('assetRightsStatus', 'owned')->set('assetAnswerable', $answerable)
            ->call('attachAssets')->assertHasNoErrors();
        $first = collect($studio->get('blocks'))->search(fn (array $block): bool => $block['type'] === 'image');
        $studio->set('blocks.'.$first.'.data.caption', 'White to move and mate in one.')->call('saveRevision')->assertHasNoErrors();

        return [$owner, $space, app(PublishSpaceContent::class)->execute($content->fresh(), $owner->user)];
    }

    private function student(GroupSpace $space, Actor $owner): Actor
    {
        $token = GroupInvitation::issueToken();
        GroupInvitation::create(['group_id' => $space->group_id, 'invited_by_actor_id' => $owner->id, 'join_mode' => GroupInvitation::JOIN_DIRECT, 'token' => $token, 'expires_at' => now()->addDay(), 'uses_count' => 0]);
        $actor = Actor::factory()->create();
        $this->actingAs($actor->user)->post(route('invitations.accept', $token));

        return $actor;
    }

    /** @return list<SpaceContentBlock> */
    private function imageBlocks(SpaceContent $content): array
    {
        return $content->fresh()->activeRevisionRecord()->blocks()->where('type', 'image')->orderBy('position')->get()->all();
    }

    public function test_six_images_chosen_together_become_six_separate_items_with_their_own_placement_and_reference(): void
    {
        [, , $content] = $this->worksheet();
        $blocks = $this->imageBlocks($content);

        $this->assertCount(6, $blocks);
        $this->assertCount(6, array_unique(array_map(fn (SpaceContentBlock $b) => $b->uuid, $blocks)));
        $this->assertCount(6, array_unique(array_map(fn (SpaceContentBlock $b) => $b->data['asset_placement_uuid'], $blocks)));
        $this->assertSame(6, Asset::query()->count());
        foreach ($blocks as $block) {
            $this->assertSame('exercise', SectionSemantics::of($block)['role'], 'each item is its own answerable section');
        }
        $this->assertSame(['position-1.png', 'position-2.png', 'position-3.png', 'position-4.png', 'position-5.png', 'position-6.png'], array_map(fn ($b) => $b->data['filename'], $blocks), 'in the order they were chosen');
    }

    public function test_the_composer_names_each_item_and_a_reader_sees_each_exactly_once_with_its_own_menu(): void
    {
        [$owner, $space, $content] = $this->worksheet();
        $student = $this->student($space, $owner);

        Livewire::actingAs($owner->user)->test(ContentStudio::class, ['context' => $content->context, 'content' => $content->fresh()])
            ->assertSeeHtml('data-studio-media-ref="Image 1"')->assertSeeHtml('data-studio-media-ref="Image 6"');

        $html = Livewire::actingAs($student->user)->test(ContentShow::class, ['context' => $content->context, 'content' => $content])->html();
        $this->assertSame(6, substr_count($html, 'data-media-item'), 'each image renders once');
        $this->assertSame(6, substr_count($html, 'data-media-menu'), 'each image has its own menu');
        foreach (range(1, 6) as $n) {
            $this->assertSame(1, substr_count($html, 'data-media-ref="Image '.$n.'"'));
        }
        // Reachable without hover on touch screens; revealed by hover only where a pointer exists.
        $this->assertStringContainsString('[@media(hover:none)]:opacity-100', $html);
        $this->assertStringContainsString('max-sm:opacity-100', $html);
        // The author's caption stays with its image.
        $this->assertStringContainsString('White to move and mate in one.', $html);
    }

    public function test_answering_image_one_attaches_only_to_image_one_and_never_touches_the_caption(): void
    {
        [$owner, $space, $content] = $this->worksheet();
        $student = $this->student($space, $owner);
        $blocks = $this->imageBlocks($content);

        $reader = Livewire::actingAs($student->user)->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
            ->assertSeeHtml('data-context-purpose="answer"')
            ->call('openContextComposer', 'answer', ['target_type' => 'block', 'target_uuid' => $blocks[0]->uuid, 'field_key' => null, 'selector' => ['label' => 'Image 1']])
            ->set('annotationBody', 'Qh7#')->call('postAnnotation')->assertHasNoErrors();

        $answer = SpaceContentAnnotation::query()->sole();
        $this->assertSame(SpaceContentAnnotation::KIND_ANSWER, $answer->kind);
        $this->assertSame('Qh7#', $answer->body);
        $this->assertSame([$blocks[0]->uuid], $answer->anchors->pluck('target_uuid')->all(), 'only image 1');
        $this->assertSame('Image 1', $answer->anchors->first()->selector['label'], 'the item is known without typing an identifier');

        // The author's caption is untouched and different from the answer.
        $this->assertSame('White to move and mate in one.', $blocks[0]->fresh()->data['caption']);
        $this->assertStringNotContainsString('Qh7#', json_encode($blocks[0]->fresh()->data));

        // Answers 2-6 are independent: image 2 can be answered separately.
        $reader->call('openContextComposer', 'answer', ['target_type' => 'block', 'target_uuid' => $blocks[1]->uuid, 'field_key' => null, 'selector' => ['label' => 'Image 2']])
            ->set('annotationBody', 'Rxe8#')->call('postAnnotation')->assertHasNoErrors();
        $byBlock = SpaceContentAnnotation::query()->with('anchors')->get()->mapWithKeys(fn ($a) => [$a->anchors->first()->target_uuid => $a->body]);
        $this->assertSame(['Qh7#', 'Rxe8#'], [$byBlock[$blocks[0]->uuid], $byBlock[$blocks[1]->uuid]]);
        $this->assertArrayNotHasKey($blocks[2]->uuid, $byBlock->all());
    }

    public function test_images_that_are_not_marked_answerable_cannot_be_answered(): void
    {
        [$owner, $space, $content] = $this->worksheet(answerable: false);
        $student = $this->student($space, $owner);
        $block = $this->imageBlocks($content)[0];

        Livewire::actingAs($student->user)->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
            ->assertDontSeeHtml('data-section-answer');
        $this->expectException(HttpException::class);
        app(AddSpaceContentAnnotation::class)->execute($content, $content->activeRevisionRecord(), $student->user, 'Qh7#', null, SpaceContentAnnotation::KIND_ANSWER, anchors: [
            ['target_type' => 'block', 'target_uuid' => $block->uuid, 'field_key' => null, 'selector' => ['label' => 'Image 1']],
        ]);
    }

    public function test_outsiders_and_guests_get_no_menu_and_cannot_respond(): void
    {
        [$owner, , $content] = $this->worksheet();
        $outsider = Actor::factory()->create();

        $this->actingAs($outsider->user)->get(route('contexts.contents.show', [$content->context, $content]))->assertForbidden();
        $block = $this->imageBlocks($content)[0];
        $this->expectException(AuthorizationException::class);
        app(AddSpaceContentAnnotation::class)->execute($content, $content->activeRevisionRecord(), $outsider->user, 'Qh7#', null, SpaceContentAnnotation::KIND_ANSWER, anchors: [
            ['target_type' => 'block', 'target_uuid' => $block->uuid, 'field_key' => null, 'selector' => ['label' => 'Image 1']],
        ]);
    }

    public function test_references_read_correctly_in_persian_and_english(): void
    {
        [$owner, $space, $content] = $this->worksheet();
        $student = $this->student($space, $owner);

        foreach (['en' => 'Image 3', 'fa' => 'تصویر 3'] as $locale => $label) {
            app()->setLocale($locale);
            Livewire::actingAs($student->user)->test(ContentShow::class, ['context' => $content->context, 'content' => $content])
                ->assertSeeHtml('data-media-ref="'.$label.'"')->assertSeeHtml('dir="auto"');
        }
    }
}
