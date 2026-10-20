{{-- "Correct" marks on an answer: an endorsement by someone allowed to endorse (the author or a role the author chose). --}}
@php
    $viewerMarked = $answer->endorsements->contains('actor_id', auth()->user()?->actor?->id);
@endphp
@if ($answer->endorsements->isNotEmpty() || ($canEndorse && $canInteract))
    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs" data-answer-verdict>
        @if ($answer->endorsements->isNotEmpty())
            <flux:badge color="green" size="sm" icon="check-badge" data-answer-correct>{{ __('sections.marked_correct_by', ['names' => $answer->endorsements->map(fn ($endorsement) => $endorsement->actor?->user?->username)->filter()->join(', ')]) }}</flux:badge>
        @endif
        @if ($canEndorse && $canInteract)
            <button type="button" wire:click="toggleEndorsement('{{ $answer->uuid }}')" class="font-medium underline underline-offset-4" style="color: var(--content-accent)" data-answer-mark>
                {{ $viewerMarked ? __('sections.unmark_correct') : __('sections.mark_correct') }}
            </button>
        @endif
    </div>
@endif
