<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Support\Ai\OpenAiChatClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_chat_uses_responses_api_without_provider_storage(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.openai.api_key', 'test-key');
        config()->set('ai.openai.chat_model', 'gpt-test');
        config()->set('ai.openai.base_url', 'https://api.openai.test/v1');
        config()->set('ai.openai.max_output_tokens', 900);

        Http::fake([
            'https://api.openai.test/v1/responses' => Http::response([
                'id' => 'resp_chat_123',
                'model' => 'gpt-test-2026-09-29',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Yes. The in-app AI connection works.',
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 12,
                    'output_tokens' => 9,
                    'total_tokens' => 21,
                ],
            ]),
        ]);

        $result = app(OpenAiChatClient::class)->respond([
            ['role' => 'user', 'content' => 'Can you hear me?'],
            ['role' => 'assistant', 'content' => 'Yes.'],
            ['role' => 'user', 'content' => 'Confirm this is inside IET.'],
        ]);

        $this->assertSame('openai', $result['provider']);
        $this->assertSame('gpt-test-2026-09-29', $result['model']);
        $this->assertSame('resp_chat_123', $result['external_response_id']);
        $this->assertSame('Yes. The in-app AI connection works.', $result['text']);
        $this->assertSame(21, $result['usage']['total_tokens']);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request['model'] === 'gpt-test'
                && $request['store'] === false
                && $request['max_output_tokens'] === 900
                && $request['input'][0]['role'] === 'user'
                && $request['input'][0]['content'] === 'Can you hear me?'
                && $request['input'][1]['role'] === 'assistant'
                && $request['input'][2]['content'] === 'Confirm this is inside IET.';
        });
    }

    public function test_chat_fails_closed_when_api_key_is_missing(): void
    {
        config()->set('ai.provider', 'openai');
        config()->set('ai.openai.api_key', null);

        try {
            app(OpenAiChatClient::class)->respond([
                ['role' => 'user', 'content' => 'Hello'],
            ]);

            $this->fail('Unconfigured AI chat should fail closed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        Http::assertNothingSent();
    }

    public function test_verified_user_can_open_ai_chat_lab(): void
    {
        $actor = Actor::factory()->create();

        $response = $this->actingAs($actor->user)->get(route('ai.chat'));

        $response->assertOk();
        $response->assertSee(__('ai.chat.title'));
        $response->assertSee(__('ai.chat.not_configured'));
    }
}
