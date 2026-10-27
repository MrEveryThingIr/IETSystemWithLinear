{{-- What each side can do with one report. $f report, $verifier, $user. --}}
@if ($f->status === \App\FulfillmentStatus::Submitted && Gate::forUser($user)->allows('review', $f))
    <div class="mt-2 space-y-2" data-review>
        <div class="grid gap-2 sm:grid-cols-2">
            <flux:input size="sm" inputmode="decimal" wire:model="text.confirm-{{ $f->id }}" :label="__('deals.work.confirm_quantity', ['reported' => $f->quantity + 0])" :placeholder="(string) ($f->quantity + 0)" />
            <flux:input size="sm" wire:model="text.review-{{ $f->id }}" :label="__('deals.work.review_note')" maxlength="500" />
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" variant="primary" wire:click="review({{ $f->id }}, 'accepted')">{{ __('deals.work.accept') }}</flux:button>
            <flux:button size="sm" wire:click="review({{ $f->id }}, 'clarification_requested')">{{ __('deals.work.ask') }}</flux:button>
            <flux:button size="sm" variant="danger" wire:click="review({{ $f->id }}, 'rejected')">{{ __('deals.work.reject') }}</flux:button>
        </div>
        <p class="text-xs text-zinc-500">{{ __('deals.work.confirm_hint') }}</p>
    </div>
@endif

{{-- The performer corrects after a question or a refusal --}}
@if (Gate::forUser($user)->allows('correct', $f))
    <div class="mt-2 flex flex-wrap items-end gap-2">
        <flux:input size="sm" inputmode="decimal" wire:model="text.qty-fix-{{ $f->id }}" :label="__('deals.work.quantity')" placeholder="{{ $f->quantity + 0 }}" class="max-w-28" />
        <flux:input size="sm" wire:model="text.notes-fix-{{ $f->id }}" :label="__('deals.work.notes')" maxlength="500" />
        <flux:button size="sm" wire:click="correct({{ $f->id }}, $wire.text['qty-fix-{{ $f->id }}'] || '{{ $f->quantity + 0 }}')">{{ __('deals.work.send_correction') }}</flux:button>
    </div>
@endif

{{-- Either side may dispute a decided report; the other side resolves --}}
@if (Gate::forUser($user)->allows('openDispute', $f))
    <details class="mt-2"><summary class="cursor-pointer text-xs text-zinc-500">{{ __('deals.work.dispute') }}</summary>
        <div class="mt-1 flex flex-wrap items-end gap-2">
            <flux:input size="sm" wire:model="text.dispute-{{ $f->id }}" :label="__('deals.work.dispute_reason')" maxlength="500" />
            <flux:button size="sm" variant="danger" wire:click="dispute({{ $f->id }})">{{ __('deals.work.dispute_open') }}</flux:button>
        </div>
    </details>
@endif
@if ($f->dispute && Gate::forUser($user)->allows('resolve', $f->dispute))
    <div class="mt-2 space-y-2">
        <p class="text-xs" dir="auto">{{ $f->dispute->reason }}</p>
        <flux:input size="sm" wire:model="text.resolve-{{ $f->dispute->id }}" :label="__('deals.work.review_note')" maxlength="500" />
        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" wire:click="resolve({{ $f->dispute->id }}, 'accepted')">{{ __('deals.work.resolve_accept') }}</flux:button>
            <flux:button size="sm" wire:click="resolve({{ $f->dispute->id }}, 'rejected')">{{ __('deals.work.resolve_reject') }}</flux:button>
        </div>
    </div>
@endif
