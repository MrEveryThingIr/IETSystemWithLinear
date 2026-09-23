<?php

namespace Tests\Feature;

use App\Actions\Ai\ApplyContentChanges;
use App\Actions\Ai\PlanContentChanges;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\Actor;
use App\Models\AiAssistanceRun;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiContentAssistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_plan_and_apply_structured_ai_changes_without_direct_database_authority(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.openai.api_key', 'test-key');
        config()->set('ai.openai.model', 'gpt-test');
        config()->set('ai.openai.base_url', 'https://api.openai.test/v1');

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($this->proposal(), JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->systemBlueprint('note-diary')->activeVersionRecord();
        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Original title',
            ['body' => 'Original body.'],
        );

        $run = app(PlanContentChanges::class)->execute(
            $content,
            $actor->user,
            'Improve the body and add a callout.',
        );

        $this->assertSame(AiAssistanceRun::STATUS_PLANNED, $run->status);
        $this->assertSame('resp_123', $run->external_response_id);
        $this->assertSame($content->draft_revision_id, $run->base_revision_id);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.openai.test/v1/responses'
            && $request['store'] === false
            && $request['text']['format']['type'] === 'json_schema');

        $applied = app(ApplyContentChanges::class)->execute($run, $actor->user);

        $this->assertSame(AiAssistanceRun::STATUS_APPLIED, $applied->status);
        $this->assertNotNull($applied->applied_revision_id);

        $draft = $content->refresh()->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);
        $this->assertSame('Improved title', $draft->title);
        $this->assertSame('Improved body.', $draft->payload['body']);
        $this->assertSame('minimal', $draft->render_template_key);
        $this->assertTrue($draft->blocks()->where('type', 'callout')->exists());

        $this->assertDatabaseCount('ai_assistance_runs', 1);
    }

    public function test_stale_ai_proposal_cannot_overwrite_a_newer_content_revision(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.openai.api_key', 'test-key');
        config()->set('ai.openai.model', 'gpt-test');
        config()->set('ai.openai.base_url', 'https://api.openai.test/v1');

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp_stale',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($this->proposal(), JSON_THROW_ON_ERROR),
                    ]],
                ]],
            ]),
        ]);

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->systemBlueprint('note-diary')->activeVersionRecord();
        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Original',
            ['body' => 'One'],
        );

        $run = app(PlanContentChanges::class)->execute($content, $actor->user, 'Improve this.');

        app(ReviseSpaceContent::class)->execute(
            $content,
            $actor->user,
            'Human edit',
            ['body' => 'Human changed this first.'],
        );

        try {
            app(ApplyContentChanges::class)->execute($run, $actor->user);
            $this->fail('A stale AI proposal should not apply.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(AiAssistanceRun::STATUS_PLANNED, $run->refresh()->status);
        $this->assertSame('Human edit', $content->refresh()->draftRevisionRecord()?->title);
    }

    public function test_ai_assistance_requires_server_configuration_and_changes_nothing_when_unconfigured(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.openai.api_key', null);

        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $version = $this->systemBlueprint('note-diary')->activeVersionRecord();
        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Original',
            ['body' => 'Unchanged'],
        );

        $beforeRevision = $content->draft_revision_id;

        try {
            app(PlanContentChanges::class)->execute($content, $actor->user, 'Improve this.');
            $this->fail('Unconfigured AI should fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertSame($beforeRevision, $content->refresh()->draft_revision_id);
        $this->assertDatabaseCount('ai_assistance_runs', 0);
    }

    private function systemBlueprint(string $slug): ContentBlueprint
    {
        app(EnsureSystemContentBlueprints::class)->execute();

        return ContentBlueprint::query()->where('slug', $slug)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function proposal(): array
    {
        return [
            'summary' => 'Improve the note while keeping the change scoped.',
            'apply_title' => true,
            'title' => 'Improved title',
            'field_updates' => [
                ['key' => 'body', 'value' => 'Improved body.'],
            ],
            'block_operations' => [[
                'operation' => 'append',
                'target_logical_uuid' => '',
                'block' => [
                    'type' => 'callout',
                    'text' => 'Remember the main point.',
                    'level' => 2,
                    'attribution' => '',
                    'items' => [],
                    'ordered' => false,
                    'tone' => 'info',
                    'field_key' => '',
                    'style' => [
                        'text_color' => '',
                        'background_color' => '',
                        'accent_color' => '',
                        'alignment' => '',
                        'emphasis' => 'callout',
                    ],
                ],
            ]],
            'apply_presentation' => true,
            'presentation' => [
                'base_key' => 'minimal',
                'background' => '#ffffff',
                'surface' => '#ffffff',
                'text' => '#09090b',
                'muted' => '#71717a',
                'accent' => '#18181b',
                'border' => '#f4f4f5',
                'content_width' => 'reading',
                'font_scale' => 'comfortable',
                'radius' => 'none',
                'heading_style' => 'plain',
                'media_style' => 'contained',
            ],
            'media_requests' => [],
        ];
    }
}
