<?php

namespace App\Livewire\Platform;

use App\Actions\Development\CaptureDevelopmentOrigin;
use App\Models\DevelopmentOrigin;
use App\Models\User;
use App\PlatformCapability;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Development Origins')]
class DevelopmentOrigins extends Component
{
    use WithPagination;
    public string $sourceType = 'chatgpt';

    public string $sourceUrl = '';

    public string $title = '';

    public string $summary = '';

    public string $phaseKey = '';

    public string $systemVersion = '';

    public string $branch = '';

    public string $baselineCommitSha = '';

    public string $resultCommitSha = '';

    public string $repositoryPaths = '';

    public string $occurredAt = '';

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function capture(CaptureDevelopmentOrigin $capture): void
    {
        $this->validate([
            'sourceType' => ['required', 'string'],
            'sourceUrl' => ['nullable', 'string', 'max:2000'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:20000'],
            'phaseKey' => ['nullable', 'string', 'max:80'],
            'systemVersion' => ['nullable', 'string', 'max:80'],
            'branch' => ['nullable', 'string', 'max:160'],
            'baselineCommitSha' => ['nullable', 'string', 'max:40'],
            'resultCommitSha' => ['nullable', 'string', 'max:40'],
            'repositoryPaths' => ['nullable', 'string', 'max:8000'],
            'occurredAt' => ['nullable', 'date'],
        ]);

        $capture->execute(
            $this->user(),
            $this->sourceType,
            $this->sourceUrl,
            $this->title,
            $this->summary,
            $this->phaseKey,
            $this->systemVersion,
            $this->branch,
            $this->baselineCommitSha,
            $this->resultCommitSha,
            preg_split('/\R/u', $this->repositoryPaths) ?: [],
            $this->occurredAt,
        );

        $this->reset([
            'sourceUrl',
            'title',
            'summary',
            'phaseKey',
            'systemVersion',
            'branch',
            'baselineCommitSha',
            'resultCommitSha',
            'repositoryPaths',
            'occurredAt',
        ]);

        session()->flash('status', __('development.captured'));
    }

    public function render(): View
    {
        $this->authorizeView();

        $origins = DevelopmentOrigin::query()
            ->with('createdBy.user')
            ->latest('id')
            ->paginate(20);

        return view('livewire.platform.development-origins', compact('origins'));
    }

    private function authorizeView(): void
    {
        abort_unless($this->user()->hasPlatformCapability(PlatformCapability::ViewPlatformAudit), 403);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
