<?php

namespace App\Livewire\Contexts;

use App\Actions\Ai\ApplyContentChanges;
use App\Actions\Ai\PlanContentChanges;
use App\Models\AiAssistanceRun;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('layouts.app')]
#[Title('AI Content Assistant')]
class ContentAiAssistant extends Component
{
    public Context $context;

    public SpaceContent $content;

    public string $prompt = '';

    public ?string $selectedRunUuid = null;

    public ?string $assistantError = null;

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        Gate::forUser($this->user())->authorize('update', $content);

        $this->context = $context;
        $this->content = $content;
    }

    public function plan(PlanContentChanges $plan): void
    {
        $this->validate(['prompt' => ['required', 'string', 'max:8000']]);
        $this->assistantError = null;

        try {
            $run = $plan->execute($this->content, $this->user(), $this->prompt);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [422, 503], true)) {
                throw $exception;
            }

            $this->assistantError = $exception->getMessage();

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->assistantError = __('ai.provider_failed');

            return;
        }

        $this->selectedRunUuid = $run->uuid;
        $this->prompt = '';
    }

    public function apply(ApplyContentChanges $apply): void
    {
        $run = $this->selectedRun();
        abort_unless($run instanceof AiAssistanceRun, 404);

        try {
            $apply->execute($run, $this->user());
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }

            $this->assistantError = $exception->getMessage();

            return;
        }

        $this->content = $this->content->refresh();
        $this->selectedRunUuid = null;
        $this->assistantError = null;
        session()->flash('status', __('ai.applied'));
    }

    public function render(): View
    {
        $current = SpaceContent::query()->findOrFail($this->content->id);
        abort_unless((int) $current->context_id === (int) $this->context->id, 404);
        Gate::forUser($this->user())->authorize('update', $current);
        $this->content = $current;

        $selectedRun = $this->selectedRun();
        $recentRuns = AiAssistanceRun::query()
            ->where('space_content_id', $current->id)
            ->where('requested_by_actor_id', $this->user()->actor?->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('livewire.contexts.content-ai-assistant', compact('selectedRun', 'recentRuns'));
    }

    private function selectedRun(): ?AiAssistanceRun
    {
        if ($this->selectedRunUuid === null) {
            return null;
        }

        return AiAssistanceRun::query()
            ->where('uuid', $this->selectedRunUuid)
            ->where('space_content_id', $this->content->id)
            ->where('requested_by_actor_id', $this->user()->actor?->id)
            ->first();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
