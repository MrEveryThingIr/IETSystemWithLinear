<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiChatClient
{
    public function configured(): bool
    {
        return config('ai.provider') === 'openai'
            && trim((string) config('ai.openai.api_key')) !== ''
            && trim((string) config('ai.openai.chat_model', config('ai.openai.model'))) !== '';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{
     *     provider: string,
     *     model: string,
     *     external_response_id: string|null,
     *     text: string,
     *     usage: array<string, int|null>
     * }
     */
    public function respond(array $messages): array
    {
        abort_unless(config('ai.provider') === 'openai', 503, 'The configured AI provider is unavailable.');

        $apiKey = trim((string) config('ai.openai.api_key'));
        abort_if($apiKey === '', 503, 'AI is not configured. Set OPENAI_API_KEY on the Laravel server.');

        $model = trim((string) config('ai.openai.chat_model', config('ai.openai.model')));
        abort_if($model === '', 503, 'AI chat model is not configured.');

        $input = collect($messages)
            ->take(-20)
            ->map(function (array $message): array {
                $role = (string) ($message['role'] ?? '');
                $content = trim((string) ($message['content'] ?? ''));

                abort_unless(in_array($role, ['user', 'assistant'], true), 422, 'Unsupported AI chat role.');
                abort_if($content === '' || mb_strlen($content) > 12000, 422, 'AI chat messages must contain 1-12,000 characters.');

                return [
                    'role' => $role,
                    'content' => $content,
                ];
            })
            ->values()
            ->all();

        abort_if($input === [], 422, 'Enter a message for AI chat.');

        $payload = [
            'model' => $model,
            'store' => false,
            'instructions' => 'You are an AI assistant embedded in the IET application. Answer the user directly and concisely. You may explain or propose actions, but never claim that application state changed unless the application explicitly performs that action outside this chat.',
            'input' => $input,
            'max_output_tokens' => (int) config('ai.openai.max_output_tokens', 1600),
        ];

        $response = $this->client($apiKey)
            ->post(rtrim((string) config('ai.openai.base_url'), '/').'/responses', $payload);

        if ($response->failed()) {
            $providerMessage = trim((string) $response->json('error.message'));
            $providerMessage = $providerMessage !== '' ? mb_substr($providerMessage, 0, 800) : 'Unknown provider error.';

            abort($response->status() === 429 ? 429 : 502, 'OpenAI request failed: '.$providerMessage);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('OpenAI returned an invalid response body.');
        }

        return [
            'provider' => 'openai',
            'model' => is_string($body['model'] ?? null) ? $body['model'] : $model,
            'external_response_id' => is_string($body['id'] ?? null) ? $body['id'] : null,
            'text' => $this->outputText($body),
            'usage' => [
                'input_tokens' => is_numeric($body['usage']['input_tokens'] ?? null) ? (int) $body['usage']['input_tokens'] : null,
                'output_tokens' => is_numeric($body['usage']['output_tokens'] ?? null) ? (int) $body['usage']['output_tokens'] : null,
                'total_tokens' => is_numeric($body['usage']['total_tokens'] ?? null) ? (int) $body['usage']['total_tokens'] : null,
            ],
        ];
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('ai.openai.timeout', 60));
    }

    /** @param  array<string, mixed>  $response */
    private function outputText(array $response): string
    {
        if (is_string($response['output_text'] ?? null) && trim($response['output_text']) !== '') {
            return trim($response['output_text']);
        }

        $parts = [];

        foreach (($response['output'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)
                    && trim($content['text']) !== '') {
                    $parts[] = trim($content['text']);
                }
            }
        }

        if ($parts !== []) {
            return implode("\n\n", $parts);
        }

        throw new RuntimeException('OpenAI returned no text output.');
    }
}
