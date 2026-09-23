<?php

namespace App\Support\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiContentPlanner
{
    /**
     * @param array<string, mixed> $snapshot
     * @return array{provider: string, model: string, external_response_id: string|null, proposal: array<string, mixed>}
     */
    public function plan(array $snapshot, string $prompt): array
    {
        abort_unless(config('ai.provider') === 'openai', 503, 'The configured AI provider is unavailable.');

        $apiKey = trim((string) config('ai.openai.api_key'));
        abort_if($apiKey === '', 503, 'AI assistance is not configured. Set OPENAI_API_KEY on the server.');

        $model = trim((string) config('ai.openai.model'));
        abort_if($model === '', 503, 'AI assistance model is not configured.');

        $payload = [
            'model' => $model,
            'store' => false,
            'instructions' => $this->instructions(),
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => json_encode([
                        'user_intent' => $prompt,
                        'content_snapshot' => $snapshot,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'iet_content_change_plan',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];

        $response = $this->client($apiKey)
            ->post(rtrim((string) config('ai.openai.base_url'), '/').'/responses', $payload)
            ->throw()
            ->json();

        if (is_array($response) === false) {
            throw new RuntimeException('AI provider returned an invalid response.');
        }

        $text = $this->outputText($response);
        $proposal = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (is_array($proposal) === false) {
            throw new RuntimeException('AI provider did not return a structured Content proposal.');
        }

        return [
            'provider' => 'openai',
            'model' => $model,
            'external_response_id' => is_string($response['id'] ?? null) ? $response['id'] : null,
            'proposal' => $proposal,
        ];
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('ai.openai.timeout', 60));
    }

    /** @param array<string, mixed> $response */
    private function outputText(array $response): string
    {
        foreach (($response['output'] ?? []) as $item) {
            if (is_array($item) === false) {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('AI provider returned no structured output text.');
    }

    private function instructions(): string
    {
        return <<<'TEXT'
You are the IET Content Studio planning assistant.

Convert the user's intent into a safe structured proposal for the existing Content revision.

Rules:
- Never output PHP, JavaScript, HTML, SQL, shell commands, URLs that execute code, or arbitrary executable behavior.
- Use only the field keys, block types, presentation choices, and logical block UUIDs present in the supplied snapshot.
- Do not remove or replace media blocks unless the user explicitly asks and the target logical UUID is present.
- New image/audio/video needs are media_requests only. Do not invent uploaded Asset identities.
- Prefer minimal edits that preserve unrelated existing content.
- A block replace/remove operation must target an existing logical UUID. Append operations use an empty target_logical_uuid.
- Field updates must use an existing field key and an appropriate JSON value.
- If the user asks for unsupported functionality, explain it in summary and leave it unapplied rather than inventing executable code.
TEXT;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $style = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'text_color' => ['type' => 'string'],
                'background_color' => ['type' => 'string'],
                'accent_color' => ['type' => 'string'],
                'alignment' => ['type' => 'string', 'enum' => ['', 'start', 'center', 'end']],
                'emphasis' => ['type' => 'string', 'enum' => ['', 'normal', 'muted', 'strong', 'callout']],
            ],
            'required' => ['text_color', 'background_color', 'accent_color', 'alignment', 'emphasis'],
        ];

        $block = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['paragraph', 'heading', 'quote', 'list', 'callout', 'divider', 'field']],
                'text' => ['type' => 'string'],
                'level' => ['type' => 'integer', 'enum' => [2, 3, 4]],
                'attribution' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => ['type' => 'string']],
                'ordered' => ['type' => 'boolean'],
                'tone' => ['type' => 'string', 'enum' => ['info', 'success', 'warning', 'danger']],
                'field_key' => ['type' => 'string'],
                'style' => $style,
            ],
            'required' => ['type', 'text', 'level', 'attribution', 'items', 'ordered', 'tone', 'field_key', 'style'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => 'string'],
                'apply_title' => ['type' => 'boolean'],
                'title' => ['type' => 'string'],
                'field_updates' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'value' => [
                                'anyOf' => [
                                    ['type' => 'string'],
                                    ['type' => 'number'],
                                    ['type' => 'boolean'],
                                    ['type' => 'null'],
                                ],
                            ],
                        ],
                        'required' => ['key', 'value'],
                    ],
                ],
                'block_operations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'operation' => ['type' => 'string', 'enum' => ['append', 'replace', 'remove']],
                            'target_logical_uuid' => ['type' => 'string'],
                            'block' => $block,
                        ],
                        'required' => ['operation', 'target_logical_uuid', 'block'],
                    ],
                ],
                'apply_presentation' => ['type' => 'boolean'],
                'presentation' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'base_key' => ['type' => 'string', 'enum' => ['article', 'lesson', 'book', 'minimal', 'showcase']],
                        'background' => ['type' => 'string'],
                        'surface' => ['type' => 'string'],
                        'text' => ['type' => 'string'],
                        'muted' => ['type' => 'string'],
                        'accent' => ['type' => 'string'],
                        'border' => ['type' => 'string'],
                        'content_width' => ['type' => 'string', 'enum' => ['narrow', 'reading', 'wide', 'full']],
                        'font_scale' => ['type' => 'string', 'enum' => ['compact', 'comfortable', 'large']],
                        'radius' => ['type' => 'string', 'enum' => ['none', 'soft', 'rounded']],
                        'heading_style' => ['type' => 'string', 'enum' => ['plain', 'serif', 'display']],
                        'media_style' => ['type' => 'string', 'enum' => ['contained', 'card', 'edge']],
                    ],
                    'required' => ['base_key', 'background', 'surface', 'text', 'muted', 'accent', 'border', 'content_width', 'font_scale', 'radius', 'heading_style', 'media_style'],
                ],
                'media_requests' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ['image', 'audio', 'video']],
                            'prompt' => ['type' => 'string'],
                            'purpose' => ['type' => 'string'],
                        ],
                        'required' => ['kind', 'prompt', 'purpose'],
                    ],
                ],
            ],
            'required' => ['summary', 'apply_title', 'title', 'field_updates', 'block_operations', 'apply_presentation', 'presentation', 'media_requests'],
        ];
    }
}
