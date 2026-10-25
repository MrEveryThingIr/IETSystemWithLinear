<?php

namespace App\Livewire\Intents;

use App\Actions\Market\ManageIntentMedia;
use App\Actions\Market\PublishIntent;
use App\Actions\Market\RestoreIntentVersion;
use App\Actions\Market\SaveIntentWorkingCopy;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\ActorProfileIntentMedia;
use App\Models\BusinessListing;
use App\Models\BusinessListingVersion;
use App\Models\Capability;
use App\Models\Group;
use App\Models\IntentDetailTemplate;
use App\Models\SpaceContent;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\Services\Business\BusinessMarketService;
use App\Support\LocalizedNumber;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentPresentation;
use App\Support\Market\IntentProfiles;
use App\Support\Market\IntentSnapshot;
use App\Support\Market\MarketMoney;
use App\Support\Market\NormalizeIntentDetails;
use App\Support\MediaLimits;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Composer extends Component
{
    use WithFileUploads;

    public $upload = null;

    public bool $rights = false;

    public string $uploadCaption = '';

    public string $uploadVisibility = 'same';

    #[Locked]
    public ?ActorProfileIntent $intent = null;

    #[Url(as: 'as')]
    public string $ownerKey = '';

    #[Url]
    public string $side = '';

    public string $intention = 'need';

    public array $core = [];

    public array $details = [];

    public string $priceMode = 'ask';

    public string $price = '';

    public string $maximumPrice = '';

    public string $conceptLabel = '';

    public ?int $conceptId = null;

    public string $capabilityUuid = '';

    public string $referenceSearch = '';

    public string $previewAudience = 'public';

    public string $detailType = 'text';

    public string $templateName = '';

    public string $notice = '';

    public string $screen = 'write';

    public string $topic = '';

    public bool $replaceLegacyPrice = false;

    public function mount(?ActorProfileIntent $intent = null): void
    {
        $user = $this->user();
        $parties = app(PartyAuthority::class)->partiesFor($user);
        if ($intent !== null && $intent->exists) {
            Gate::forUser($user)->authorize('update', $intent);
            $this->intent = $intent;
            $this->ownerKey = $intent->publisher()->key();
            $snapshot = IntentSnapshot::fromRecord(DB::table('actor_profile_intents')->where('id', $intent->id)->first());
            $working = $intent->working_copy ?? $snapshot;
            $this->core = $working['core'];
            $this->details = array_map(fn ($row): array => [...$row, 'editor_key' => (string) Str::uuid(), 'value' => $row['payload']['value'] ?? null, 'options' => $row['payload']['options'] ?? []], $working['details']);
            $this->intention = $intent->kind->value;
            $this->conceptId = $intent->concept_id;
            $this->conceptLabel = $intent->concept?->labels()->where('locale', $user->locale)->value('label') ?? '';
            $this->price = MarketMoney::legacyToman($this->core['cash_min'] ?? null, $this->core['currency_code'] ?? null) ?? '';
            $this->maximumPrice = MarketMoney::legacyToman($this->core['cash_max'] ?? null, $this->core['currency_code'] ?? null) ?? '';
            $this->topic = (string) ($intent->metadata['composer_topic'] ?? '');
            $this->priceMode = ($this->core['exchange_preference'] ?? '') === 'free' ? 'free' : ($this->price === '' ? 'ask' : ($this->maximumPrice !== '' && $this->maximumPrice !== $this->price ? 'range' : 'fixed'));
        } else {
            $this->core = ['title' => '', 'description' => '', 'schedule_kind' => 'ongoing', 'visibility' => 'authenticated',
                'timezone' => TemporalPreferences::timezoneFor($user), 'subject_kind' => 'other', 'arrangement_kind' => 'other',
                'exchange_preference' => 'discuss_later', 'recurrence_interval' => 1, 'round_trip' => false];
            if (! $parties->contains(fn ($entry): bool => $entry['party']->key() === $this->ownerKey)) {
                $this->ownerKey = $parties->first()['party']->key();
            }
            $this->choose(in_array($this->side, ['need', 'offer'], true) ? $this->side : 'need');
        }
        $this->syncProfileRows();
    }

    public function choose(string $intention): void
    {
        abort_unless(in_array($intention, ['need', 'offer', 'service', 'person', 'sell', 'rent', 'exchange', 'activity'], true), 422);
        if ($this->intent !== null && $this->intent->status !== ProfileIntentStatus::Draft) {
            // Published semantic identity is immutable; a draft's text/details remain editable.
            $kind = in_array($intention, ['offer', 'service', 'sell'], true) ? 'offer' : 'need';
            abort_unless($kind === $this->intent->kind->value, 422, __('composer.identity_fixed'));
        }
        $this->intention = $intention;
        $this->core['subject_kind'] = match ($intention) {
            'service' => 'service', 'sell' => 'good', 'rent' => 'property', 'activity', 'person' => 'collaboration', default => 'other'
        };
        $this->core['arrangement_kind'] = match ($intention) {
            'service' => 'service', 'sell' => 'ownership_transfer', 'rent' => 'temporary_use', 'activity', 'person' => 'collaboration', default => 'other'
        };
        if ($intention === 'exchange') {
            $this->priceMode = 'exchange';
        }
        $topics = IntentProfiles::topics($intention);
        if (count($topics) === 1) {
            $this->chooseTopic($topics[0]);

            return;
        }
        if ($this->topic !== '' && ! in_array($this->topic, $topics, true)) {
            $this->topic = '';
        }
        $this->chooseTopic($this->topic);
    }

    public function chooseTopic(string $topic): void
    {
        abort_unless($topic === '' || in_array($topic, IntentProfiles::TOPICS, true), 422);
        $this->topic = $topic;
        $profile = IntentProfiles::all()[$topic] ?? null;
        if ($profile !== null) {
            $this->core['subject_kind'] = $profile['subject'];
            $this->core['arrangement_kind'] = IntentProfiles::arrangement($topic, (string) ($this->core['arrangement_kind'] ?? 'other'));
        }
        $this->syncProfileRows();
    }

    public function chooseArrangement(string $arrangement): void
    {
        $allowed = IntentProfiles::all()[$this->topic]['arrangements'] ?? [];
        abort_unless(in_array($arrangement, $allowed, true), 422);
        $this->core['arrangement_kind'] = $arrangement;
        // Renting is priced per month and selling is a total; never overwrite a price the person set.
        if ($arrangement === 'temporary_use' && $this->priceMode === 'ask') {
            $this->priceMode = 'per_unit';
            $this->core['cash_basis'] = 'month';
        } elseif ($arrangement !== 'temporary_use' && $this->priceMode === 'per_unit' && ($this->core['cash_basis'] ?? '') === 'month') {
            $this->priceMode = 'fixed';
            $this->core['cash_basis'] = 'total';
        }
        $this->syncProfileRows();
    }

    /** Keep exactly the profile's questions: add missing essentials, drop empty rows that no longer apply. */
    private function syncProfileRows(): void
    {
        $answers = [];
        foreach ($this->details as $row) {
            if (! empty($row['canonical_key'])) {
                $answers[$row['canonical_key']] = $row['value'] ?? null;
            }
        }
        $fields = IntentProfiles::fields($this->topic, $this->intention, (string) ($this->core['arrangement_kind'] ?? 'other'), $answers);
        $visible = [...$fields['essential'], ...$fields['suggested']];
        $this->details = array_values(array_filter($this->details, fn (array $row): bool => empty($row['canonical_key'])
            || in_array($row['canonical_key'], $visible, true) || ! self::emptyAnswer($row['value'] ?? null)));
        $present = array_column($this->details, 'canonical_key');
        foreach ($fields['essential'] as $key) {
            if (! in_array($key, $present, true)) {
                $this->details[] = $this->newRow($key);
            }
        }
    }

    private static function emptyAnswer(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    /** @return array<string, mixed> */
    private function newRow(?string $key): array
    {
        $spec = $key === null ? null : (IntentDetailCatalog::all()[$key] ?? abort(422));
        $type = $spec['type'] ?? $this->detailType;

        return ['editor_key' => (string) Str::uuid(), 'canonical_key' => $key, 'label' => $key === null ? '' : __('composer.details.'.$key),
            'type' => $type, 'value' => match ($type) {
                'date_range', 'reference', 'multiple_choice' => [], 'boolean' => $key === null ? '' : false, default => ''
            }, 'visibility' => 'same', 'unit' => $spec['unit'] ?? '', 'group_label' => '', 'options' => $spec['options'] ?? []];
    }

    public function updatedOwnerKey(): void
    {
        $this->publisher();
        $this->reset('conceptId', 'capabilityUuid');
        if (($this->core['visibility'] ?? '') === 'members' && $this->publisher()->kind === Party::ACTOR) {
            $this->core['visibility'] = 'authenticated';
        }
    }

    public function useCapability(string $uuid): void
    {
        abort_unless($this->intent === null, 422);
        $capability = Capability::query()->heldBy($this->publisher())->active()->where('uuid', $uuid)->firstOrFail();
        $this->choose('service');
        $this->capabilityUuid = $uuid;
        $this->conceptId = $capability->concept_id;
        if (trim((string) ($this->core['title'] ?? '')) === '') {
            $this->core['title'] = $capability->title ?? '';
        }
    }

    public function addDetail(?string $key = null): void
    {
        abort_if(count($this->details) >= 50, 422);
        $this->details[] = $this->newRow($key);
        $this->dispatch('composer-detail-added', index: count($this->details) - 1);
    }

    public function removeDetail(int $index): void
    {
        unset($this->details[$index]);
        $this->details = array_values($this->details);
        $this->dispatch('composer-detail-removed');
    }

    public function moveDetail(int $index, int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $next = $index + $direction;
        if (isset($this->details[$index], $this->details[$next])) {
            [$this->details[$index], $this->details[$next]] = [$this->details[$next], $this->details[$index]];
        }
    }

    public function saveDraft(): void
    {
        $this->saveWorking();
        $this->notice = __('composer.draft_saved');
    }

    public function publish(): void
    {
        $this->saveWorking();
        app(PublishIntent::class)->execute($this->user(), $this->intent);
        $this->intent = $this->intent->fresh();
        $this->notice = __('composer.published');
    }

    public function discard(): void
    {
        abort_unless($this->intent !== null, 422);
        app(PublishIntent::class)->discard($this->user(), $this->intent);
        $this->mount($this->intent->fresh());
        $this->notice = __('composer.discarded');
    }

    public function restoreVersion(string $uuid): void
    {
        abort_unless($this->intent !== null, 404);
        $version = $this->intent->versions()->where('uuid', $uuid)->firstOrFail();
        app(RestoreIntentVersion::class)->execute($this->user(), $this->intent, $version);
        $this->mount($this->intent->fresh());
        $this->notice = __('composer.restored', ['version' => $version->version]);
    }

    public function useNewCatalogVersion(): void
    {
        $catalog = $this->catalog();
        abort_unless($catalog !== null && $catalog['newer'], 404);
        $listing = $this->intent->businessListing;
        $fresh = app(BusinessMarketService::class)->publishListingOffer($listing->business, $listing, $this->user());
        $this->redirectRoute('intents.compose.edit', $fresh, navigate: true);
    }

    /** @return array{title: string, version: int, newer: bool, edit: string}|null */
    private function catalog(): ?array
    {
        $listing = $this->intent?->businessListing;
        if ($listing === null) {
            return null;
        }
        $linked = BusinessListingVersion::query()->findOrFail($this->intent->business_listing_version_id);
        $latest = $listing->publishedVersion;

        return ['title' => (string) $linked->title, 'version' => (int) $linked->version_number,
            'newer' => $latest !== null && $latest->id !== $linked->id,
            'edit' => route('businesses.catalog.listings.edit', [$listing->business, $listing])];
    }

    public function uploadMedia(): void
    {
        $this->validate(['upload' => ['required', 'file', 'max:'.MediaLimits::kilobytes('asset')],
            'rights' => ['accepted'], 'uploadCaption' => ['nullable', 'string', 'max:500'], 'uploadVisibility' => ['in:same,private']]);
        $this->saveWorking();
        app(ManageIntentMedia::class)->add($this->user(), $this->intent, $this->upload, $this->uploadCaption, $this->uploadVisibility, publish: false);
        $this->reset('upload', 'uploadCaption', 'rights');
    }

    public function removeMedia(string $uuid): void
    {
        $this->saveWorking();
        app(ManageIntentMedia::class)->remove($this->user(), $this->mediaItem($uuid), publish: false);
    }

    public function moveMediaUp(string $uuid): void
    {
        $this->saveWorking();
        app(ManageIntentMedia::class)->moveUp($this->user(), $this->mediaItem($uuid), publish: false);
    }

    public function setMediaVisibility(string $uuid, string $visibility): void
    {
        $this->saveWorking();
        app(ManageIntentMedia::class)->setVisibility($this->user(), $this->mediaItem($uuid), $visibility);
    }

    private function mediaItem(string $uuid): ActorProfileIntentMedia
    {
        abort_unless($this->intent !== null, 404);

        return $this->intent->media()->where('uuid', $uuid)->firstOrFail();
    }

    public function saveTemplate(): void
    {
        $this->validate(['templateName' => ['required', 'string', 'max:120']]);
        $publisher = $this->publisher();
        abort_unless(in_array($publisher->kind, [Party::ACTOR, Party::BUSINESS], true), 422);
        $rows = app(NormalizeIntentDetails::class)->execute($this->user(), $this->editableDetails(shape: true), shapeOnly: true);
        $fields = array_map(function ($row): array {
            $options = $row['payload']['options'] ?? [];
            unset($row['payload']);

            return [...$row, 'options' => $options];
        }, $rows);
        IntentDetailTemplate::query()->create(['owner_actor_id' => $publisher->kind === Party::ACTOR ? $publisher->id : null,
            'owner_business_id' => $publisher->kind === Party::BUSINESS ? $publisher->id : null, 'name' => $this->templateName, 'fields' => $fields]);
        $this->notice = __('composer.template_saved');
    }

    public function applyTemplate(string $uuid): void
    {
        $publisher = $this->publisher();
        $template = IntentDetailTemplate::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless(($publisher->kind === Party::ACTOR && $template->owner_actor_id === $publisher->id)
            || ($publisher->kind === Party::BUSINESS && $template->owner_business_id === $publisher->id), 403);
        abort_if(count($this->details) + count($template->fields) > 50, 422);
        foreach ($template->fields as $field) {
            $this->details[] = [...$field, 'editor_key' => (string) Str::uuid(), 'value' => in_array($field['type'], ['multiple_choice', 'date_range', 'reference'], true) ? [] : ''];
        }
    }

    public function updatedDetails(mixed $value, ?string $key = null): void
    {
        if ($key !== null && preg_match('/^(\d+)\.optionsText$/D', $key, $match) && is_string($value)) {
            $this->details[(int) $match[1]]['options'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
        }
        $this->syncProfileRows();
    }

    private function saveWorking(): void
    {
        $user = $this->user();
        if (trim((string) ($this->core['title'] ?? '')) === '' && trim((string) ($this->core['description'] ?? '')) === '') {
            throw ValidationException::withMessages(['core.description' => __('composer.describe_required')]);
        }
        $core = $this->core;
        $core['exchange_preference'] = match ($this->priceMode) {
            'free' => 'free', 'exchange' => 'open_hybrid', 'fixed', 'range', 'per_unit' => 'cash_only', default => 'discuss_later'
        };
        $fromCatalog = $this->intent?->business_listing_version_id !== null;
        if ($fromCatalog) {
            $this->priceMode = 'ask';
        }
        $preserveLegacyPrice = $fromCatalog || ($this->hasLegacyPrice() && ! $this->replaceLegacyPrice);
        $core['cash_min'] = ! $preserveLegacyPrice && in_array($this->priceMode, ['fixed', 'range', 'per_unit'], true) ? (string) MarketMoney::parse($this->price, 'price') : null;
        $core['cash_max'] = ! $preserveLegacyPrice && $this->priceMode === 'range' ? (string) MarketMoney::parse($this->maximumPrice, 'maximumPrice') : $core['cash_min'];
        $core['currency_code'] = MarketMoney::currency();
        if ($preserveLegacyPrice) {
            foreach (['cash_min', 'cash_max', 'cash_basis', 'currency_code', 'exchange_preference', ...($fromCatalog ? ['title', 'description', 'quantity', 'unit', 'location_text'] : [])] as $field) {
                $core[$field] = $this->intent->getAttribute($field);
                if ($core[$field] instanceof \BackedEnum) {
                    $core[$field] = $core[$field]->value;
                }
            }
        }
        foreach (['quantity', 'importance_percent', 'recurrence_interval', 'recurrence_day_of_month', 'return_after_days'] as $field) {
            if (is_string($core[$field] ?? null) && trim($core[$field]) !== '') {
                $core[$field] = LocalizedNumber::decimal($core[$field]);
            }
        }
        $core['cash_basis'] = $core['cash_basis'] ?? 'total';
        $publisher = $this->publisher();
        $this->intent = DB::transaction(function () use ($user, $core, $publisher): ActorProfileIntent {
            $intent = $this->intent;
            if ($intent === null) {
                $profile = app(EnsureActorProfile::class)->execute($user);
                $kind = in_array($this->intention, ['offer', 'service', 'sell'], true) ? ProfileIntentKind::Offer : ProfileIntentKind::Need;
                $capability = $this->capabilityUuid === '' ? null : Capability::query()->heldBy($publisher)->active()->where('uuid', $this->capabilityUuid)->firstOrFail();
                $intent = app(CreateActorProfileIntent::class)->execute($user, $profile, $kind, $this->conceptLabel ?: __('composer.uncategorized'), $core,
                    ['publisher' => $publisher, 'concept_id' => $this->conceptId, 'capability_id' => $capability?->id], draft: true);
            } else {
                abort_unless($intent->publisher()->key() === $publisher->key(), 403);
            }

            $kind = in_array($this->intention, ['offer', 'service', 'sell'], true) ? ProfileIntentKind::Offer : ProfileIntentKind::Need;

            $saved = app(SaveIntentWorkingCopy::class)->execute($user, $intent, $core, $this->editableDetails(), $kind);
            if (($saved->metadata['composer_topic'] ?? '') !== $this->topic) {
                $saved->forceFill(['metadata' => [...($saved->metadata ?? []), 'composer_topic' => $this->topic]])->save();
            }

            return $saved;
        }, 3);
        // Keep a reload on this draft instead of an empty composer.
        $this->js('history.replaceState(null, "", '.json_encode(route('intents.compose.edit', $this->intent, false), JSON_UNESCAPED_SLASHES).')');
    }

    public function render(): View
    {
        $user = $this->user();
        if ($this->intent !== null) {
            Gate::forUser($user)->authorize('update', $this->intent);
        }

        return view('livewire.intents.composer', ['parties' => app(PartyAuthority::class)->partiesFor($user),
            'profile' => $this->profileView(),
            'detailTypes' => NormalizeIntentDetails::TYPES, 'legacyPrice' => $this->hasLegacyPrice(),
            'publisher' => $this->publisher(),
            'versions' => $this->intent?->versions()->reorder('version', 'desc')->limit(50)->get() ?? collect(),
            'catalog' => $this->catalog(),
            'mediaItems' => $this->intent?->media()->with('asset')->get() ?? collect(),
            'referenceChoices' => $this->referenceChoices(),
            'presentation' => app(IntentPresentation::class)->build($this->previewSnapshot(), $this->previewAudience === 'owner' ? $user : null, $this->previewAudience === 'owner', $this->intent),
            'capabilities' => $this->intent === null ? Capability::query()->heldBy($this->publisher())->active()->with('concept.labels')->limit(12)->get() : collect(),
            'templates' => in_array($this->publisher()->kind, [Party::ACTOR, Party::BUSINESS], true)
                ? IntentDetailTemplate::query()->where($this->publisher()->kind === Party::BUSINESS ? 'owner_business_id' : 'owner_actor_id', $this->publisher()->id)->get()
                : collect()])->title(__('composer.title'));
    }

    /** @return array{topics: list<string>, arrangements: list<string>, essential: list<string>, suggested: list<string>, open: list<string>} */
    private function profileView(): array
    {
        $answers = [];
        foreach ($this->details as $row) {
            if (! empty($row['canonical_key'])) {
                $answers[$row['canonical_key']] = $row['value'] ?? null;
            }
        }
        $arrangement = (string) ($this->core['arrangement_kind'] ?? 'other');
        $fields = IntentProfiles::fields($this->topic, $this->intention, $arrangement, $answers);
        $present = array_column($this->details, 'canonical_key');
        $profile = IntentProfiles::all()[$this->topic] ?? null;

        return ['topics' => IntentProfiles::topics($this->intention), 'arrangements' => count($profile['arrangements'] ?? []) > 1 ? $profile['arrangements'] : [],
            'essential' => $fields['essential'], 'suggested' => array_values(array_diff($fields['suggested'], $present)),
            'open' => $profile['open'] ?? []];
    }

    private function hasLegacyPrice(): bool
    {
        return $this->intent !== null && $this->intent->currency_code !== MarketMoney::currency()
            && ($this->intent->cash_min !== null || $this->intent->cash_max !== null);
    }

    private function previewSnapshot(): array
    {
        $core = $this->core;
        if (! $this->hasLegacyPrice() || $this->replaceLegacyPrice) {
            $core['currency_code'] = MarketMoney::currency();
            $core['exchange_preference'] = $this->priceMode === 'free' ? 'free' : 'discuss_later';
            try {
                $core['cash_min'] = in_array($this->priceMode, ['fixed', 'range', 'per_unit'], true) ? (string) MarketMoney::parse($this->price) : null;
                $core['cash_max'] = $this->priceMode === 'range' ? (string) MarketMoney::parse($this->maximumPrice) : $core['cash_min'];
            } catch (ValidationException) {
                $core['cash_min'] = $core['cash_max'] = null;
            }
        }

        return ['core' => $core, 'details' => array_map(fn ($detail): array => [...$detail, 'payload' => ['value' => $detail['value'] ?? null]], $this->details),
            'media' => $this->intent === null ? [] : $this->intent->media()->get(['uuid', 'visibility', 'caption'])->toArray()];
    }

    private function referenceChoices(): array
    {
        $types = array_unique(array_filter(array_map(fn ($detail) => $detail['type'] === 'reference' ? ($detail['value']['type'] ?? 'content') : null, $this->details)));
        $choices = [];
        foreach ($types as $type) {
            $records = match ($type) {
                'content' => SpaceContent::query()->orderByDesc('id')->limit(100)->get(),
                'group' => Group::query()->orderByDesc('id')->limit(100)->get(),
                'catalog' => BusinessListing::query()->orderByDesc('id')->limit(100)->get(),
                default => collect(),
            };
            foreach ($records as $record) {
                if (! Gate::forUser($this->user())->allows('view', $record)) {
                    continue;
                }
                $label = match (true) {
                    $record instanceof Group => $record->name,
                    $record instanceof BusinessListing => $record->publishedVersion()->value('title') ?? $record->uuid,
                    default => $record->activeRevision()->value('title') ?? $record->uuid,
                };
                if ($this->referenceSearch === '' || mb_stripos($label, mb_substr($this->referenceSearch, 0, 120)) !== false) {
                    $choices[$type][] = ['id' => $record->id, 'label' => $label];
                }
            }
        }

        return $choices;
    }

    /** Published answers are only the filled ones; a template keeps every row's shape. */
    private function editableDetails(bool $shape = false): array
    {
        $answered = $shape ? $this->details : array_filter($this->details, fn (array $detail): bool => ! self::emptyAnswer($detail['value'] ?? null));

        return array_map(function (array $detail): array {
            if (isset($detail['optionsText'])) {
                $detail['options'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $detail['optionsText'])))));
            }

            return $detail;
        }, array_values($answered));
    }

    private function publisher(): Party
    {
        $entry = app(PartyAuthority::class)->partiesFor($this->user())->first(fn ($entry): bool => $entry['party']->key() === $this->ownerKey);
        abort_unless(is_array($entry), 403);

        return $entry['party'];
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->status === 'active' && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
