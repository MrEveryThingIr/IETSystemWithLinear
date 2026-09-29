<?php

namespace App\Livewire\Ai;

use App\Models\User;
use App\Support\Ai\OpenAiChatClient;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('AI Chat')]
class Chat extends Component
{
    public string $message = '';

    /** @var list<array{role: string, content: string}> */
    public array $messages = [];

    public ?string $assistantError = null;

    public ?string $lastModel = null;

    public ?string $lastResponseId = null;

    /** @var array<string, int|null> */
    public array $lastUsage = [];

    public function send(OpenAiChatClient $client): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:8000'],
        ]);

        $prompt = trim($this->message);
        $this->assistantError = null;
        $this->messages[] = ['role' => 'user', 'content' => $prompt];
        $this->messages = array_slice($this->messages, -20);
        $this->message = '';

        try {
            $response = $client->respond($this->messages);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [422, 429, 502, 503], true)) {
                throw $exception;
            }

            $this->assistantError = $exception->getMessage();

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->assistantError = __('ai.chat.failed');

            return;
        }

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $response['text'],
        ];
        $this->messages = array_values(array_slice($this->messages, -20));
        $this->lastModel = $response['model'];
        $this->lastResponseId = $response['external_response_id'];
        $this->lastUsage = $response['usage'];
    }

    public function clearConversation(): void
    {
        $this->reset('message', 'messages', 'assistantError', 'lastModel', 'lastResponseId', 'lastUsage');
    }

    public function render(OpenAiChatClient $client): View
    {
        $this->user();

        return view('livewire.ai.chat', [
            'configured' => $client->configured(),
            'configuredModel' => (string) config('ai.openai.chat_model', config('ai.openai.model')),
        ]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
