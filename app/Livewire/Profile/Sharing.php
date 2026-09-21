<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\CreateProfileDisclosureGrant;
use App\Actions\Profile\RevokeProfileDisclosureGrant;
use App\Models\ActorProfile;
use App\Models\User;
use App\Support\Profile\ProfileCompletenessService;
use App\Support\Profile\ProfileDisclosureCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Sharing extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public string $recipientProfileId = '';

    public ?string $purpose = null;

    public string $expiryDays = '30';

    /** @var list<string> */
    public array $selectedItems = [];

    public function mount(ActorProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function create(CreateProfileDisclosureGrant $createGrant): void
    {
        $this->validate([
            'recipientProfileId' => ['required', 'string', 'max:500'],
            'purpose' => ['nullable', 'string', 'max:180'],
            'expiryDays' => ['required', Rule::in(['never', '7', '30', '90'])],
            'selectedItems' => ['required', 'array', 'min:1', 'max:100'],
            'selectedItems.*' => ['required', 'string', 'max:255'],
        ]);

        $recipient = ActorProfile::query()
            ->with('actor.user')
            ->where('public_id', $this->extractPublicId($this->recipientProfileId))
            ->first();

        if (! $recipient instanceof ActorProfile) {
            throw ValidationException::withMessages([
                'recipientProfileId' => __('ui.profile_sharing.invalid_recipient'),
            ]);
        }

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $createGrant->execute(
            $user,
            $this->profile,
            $recipient,
            $this->selectedItems,
            $this->purpose,
            $this->expiryDays === 'never' ? null : (int) $this->expiryDays,
        );

        $this->reset(['recipientProfileId', 'purpose', 'selectedItems']);
        $this->expiryDays = '30';

        session()->flash('status', __('ui.profile_sharing.created'));
    }

    public function revoke(string $uuid, RevokeProfileDisclosureGrant $revokeGrant): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $grant = $this->profile->disclosureGrants()->where('uuid', $uuid)->firstOrFail();
        $revokeGrant->execute($user, $grant);

        session()->flash('status', __('ui.profile_sharing.revoked_message'));
    }

    public function render(
        ProfileDisclosureCatalog $catalog,
        ProfileCompletenessService $completeness,
    ): View {
        Gate::authorize('update', $this->profile);
        $this->profile = $this->profile->fresh() ?? $this->profile;

        return view('livewire.profile.sharing', [
            'availableItems' => $catalog->available($this->profile),
            'completeness' => $completeness->summarize(
                $this->profile,
                $completeness->recommendedRequirements(),
            ),
            'grants' => $this->profile->disclosureGrants()
                ->with(['grantee.profile', 'grantee.user', 'items'])
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    private function extractPublicId(string $value): string
    {
        $trimmed = trim($value);
        $path = parse_url($trimmed, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $candidate = basename(rtrim($path, '/'));

            if ($candidate !== '' && $candidate !== 'profiles') {
                return $candidate;
            }
        }

        return $trimmed;
    }
}
