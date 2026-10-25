<?php

namespace App\Livewire\Intents;

use App\Actions\Market\ManageIntentMedia;
use App\Actions\Market\ProposeIntentField;
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
use App\Support\Market\ConstraintParser;
use App\Support\Market\DetailValue;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldLibrary;
use App\Support\Market\IntentPresentation;
use App\Support\Market\IntentProfiles;
use App\Support\Market\IntentSnapshot;
use App\Support\Market\IntentTerms;
use App\Support\Market\MarketMoney;
use App\Support\Market\NormalizeIntentDetails;
use App\Support\Market\YearSpan;
use App\Support\MediaLimits;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    /** @var list<string> the term blocks (price, place, ...) this composition has chosen to use */
    public array $terms = [];

    public bool $pickerOpen = false;

    public string $pickerSearch = '';

    public string $customLabel = '';

    public string $groupName = '';

    public string $renaming = '';

    public string $renameTo = '';

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
            $this->details = array_map(fn ($row): array => [...$row, 'editor_key' => (string) Str::uuid(), 'value' => $row['payload']['value'] ?? null, 'options' => $row['payload']['options'] ?? [],
                'op' => $row['payload']['constraint']['op'] ?? 'exact', 'min' => $row['payload']['constraint']['min'] ?? '', 'max' => $row['payload']['constraint']['max'] ?? '',
                'values' => $row['payload']['constraint']['values'] ?? [], 'note' => $row['payload']['note'] ?? '', 'showNote' => ! empty($row['payload']['note'])], $working['details']);
            $this->intention = $intent->kind->value;
            $this->conceptId = $intent->concept_id;
            $this->conceptLabel = $intent->concept?->labels()->where('locale', $user->locale)->value('label') ?? '';
            $this->price = MarketMoney::legacyToman($this->core['cash_min'] ?? null, $this->core['currency_code'] ?? null) ?? '';
            $this->maximumPrice = MarketMoney::legacyToman($this->core['cash_max'] ?? null, $this->core['currency_code'] ?? null) ?? '';
            $this->topic = (string) ($intent->metadata['composer_topic'] ?? '');
            $this->priceMode = ($this->core['exchange_preference'] ?? '') === 'free' ? 'free' : ($this->price === '' ? 'ask' : ($this->maximumPrice !== '' && $this->maximumPrice !== $this->price ? 'range' : 'fixed'));
            $this->terms = IntentTerms::populated($this->core, $this->priceMode !== 'ask' || $this->hasLegacyPrice(), (bool) $this->conceptId, $intent->media()->exists());
        } else {
            $this->core = ['title' => '', 'description' => '', 'schedule_kind' => 'ongoing', 'visibility' => 'authenticated',
                'timezone' => TemporalPreferences::timezoneFor($user), 'subject_kind' => 'other', 'arrangement_kind' => 'other',
                'exchange_preference' => 'discuss_later', 'recurrence_interval' => 1, 'round_trip' => false];
            if (! $parties->contains(fn ($entry): bool => $entry['party']->key() === $this->ownerKey)) {
                $this->ownerKey = $parties->first()['party']->key();
            }
            $this->choose(in_array($this->side, ['need', 'offer'], true) ? $this->side : 'need');
        }
    }

    public function choose(string $intention): void
    {
        abort_unless(in_array($intention, ['need', 'offer', 'service', 'person', 'sell', 'rent', 'exchange', 'activity'], true), 422);
        if ($this->intent !== null && $this->intent->status !== ProfileIntentStatus::Draft) {
            // Published semantic identity is immutable; a draft's text/details remain editable.
            $kind = in_array($intention, ['offer', 'service', 'sell'], true) ? 'offer' : 'need';
            abort_unless($kind === $this->intent->kind->value, 422, __('composer.identity_fixed'));
        }
        $previousArrangement = (string) ($this->core['arrangement_kind'] ?? 'other');
        $wasNeed = $this->isNeedSide();
        $this->intention = $intention;
        if ($wasNeed && ! $this->isNeedSide()) {
            $this->resetConstraints();
        }
        $this->core['subject_kind'] = match ($intention) {
            'service' => 'service', 'sell' => 'good', 'rent' => 'property', 'activity', 'person' => 'collaboration', default => 'other'
        };
        $this->core['arrangement_kind'] = match ($intention) {
            'service' => 'service', 'sell' => 'ownership_transfer', 'rent' => 'temporary_use', 'activity', 'person' => 'collaboration', default => 'other'
        };
        if ($intention === 'exchange') {
            $this->priceMode = 'exchange';
            $this->addTerm('price');
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
        if (in_array($previousArrangement, IntentProfiles::all()[$this->topic]['arrangements'] ?? [], true)) {
            $this->core['arrangement_kind'] = $previousArrangement;
        }
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
    }

    /**
     * Only the term blocks the person chose contribute canonical values.
     *
     * @param  array<string, mixed>  $core
     * @return array<string, mixed>
     */
    private function scopedCore(array $core, bool $fromCatalog): array
    {
        foreach (IntentTerms::BLOCKS as $block) {
            if (! in_array($block, $this->terms, true) && ! ($fromCatalog && in_array($block, ['price', 'place', 'quantity'], true))) {
                foreach (IntentTerms::owned($block) as $field) {
                    $core[$field] = in_array($field, ['round_trip', 'negotiable'], true) ? false : null;
                }
            }
        }

        return $core;
    }

    /** A row counts once it carries a value, or a wish (range, minimum, maximum, accepted set). */
    private static function answered(array $detail): bool
    {
        if (! self::emptyAnswer($detail['value'] ?? null)) {
            return true;
        }

        return ($detail['op'] ?? 'exact') !== 'exact'
            && (! self::emptyAnswer($detail['min'] ?? null) || ! self::emptyAnswer($detail['max'] ?? null) || ! self::emptyAnswer($detail['values'] ?? null));
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

        return ['editor_key' => (string) Str::uuid(), 'canonical_key' => $key, 'label' => $key === null ? '' : IntentDetailCatalog::label($key),
            'type' => $type, 'value' => match ($type) {
                'date_range', 'reference', 'multiple_choice' => [], 'boolean' => $key === null ? '' : false, default => ''
            }, 'visibility' => 'same', 'unit' => $spec['unit'] ?? '', 'group_label' => '', 'options' => $spec['options'] ?? [],
            'op' => 'exact', 'min' => '', 'max' => '', 'values' => [], 'note' => '', 'showNote' => false];
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

    private function isNeedSide(): bool
    {
        return ! in_array($this->intention, ['offer', 'service', 'sell'], true);
    }

    /** An Offer states one fact: ranges and sets from a Need become empty plain values to fill in. */
    private function resetConstraints(): void
    {
        foreach ($this->details as $index => $row) {
            if (($row['op'] ?? 'exact') !== 'exact') {
                $this->details[$index] = [...$row, 'op' => 'exact', 'min' => '', 'max' => '', 'values' => [], 'value' => in_array($row['type'], ['boolean'], true) ? false : ''];
            }
        }
    }

    /** Insert a library field the person picked; never inserted automatically, never twice. */
    public function addField(string $key): void
    {
        abort_unless(isset(IntentDetailCatalog::all()[$key]), 422);
        if (! in_array($key, array_column($this->details, 'canonical_key'), true)) {
            abort_if(count($this->details) >= 50, 422);
            $this->details[] = $this->newRow($key);
            $this->dispatch('composer-detail-added', index: count($this->details) - 1);
        }
        $this->pickerOpen = false;
        $this->pickerSearch = '';
    }

    public function addTerm(string $block): void
    {
        abort_unless(in_array($block, IntentTerms::BLOCKS, true), 422);
        if (! in_array($block, $this->terms, true)) {
            $this->terms[] = $block;
            if ($block === 'price' && $this->priceMode === 'ask') {
                $this->priceMode = ($this->core['arrangement_kind'] ?? '') === 'temporary_use' ? 'per_unit' : 'fixed';
                $this->core['cash_basis'] = $this->priceMode === 'per_unit' ? 'month' : 'total';
            }
        }
        $this->pickerOpen = false;
        $this->pickerSearch = '';
    }

    /** Removing a term clears what it owned, so nothing hidden is ever published. */
    public function removeTerm(string $block): void
    {
        abort_if($block === 'media' && $this->intent?->media()->exists(), 422);
        abort_if($block === 'category' && $this->intent !== null, 422);
        abort_if($this->catalog() !== null && in_array($block, ['price', 'place', 'quantity'], true), 422);
        $this->terms = array_values(array_diff($this->terms, [$block]));
        foreach (IntentTerms::owned($block) as $field) {
            $this->core[$field] = in_array($field, ['round_trip', 'negotiable'], true) ? false : null;
        }
        if ($block === 'price') {
            $this->priceMode = 'ask';
            $this->price = $this->maximumPrice = '';
        }
        if ($block === 'category') {
            $this->conceptId = null;
        }
        if ($block === 'when') {
            $this->core['schedule_kind'] = 'ongoing';
        }
    }

    public function openPicker(): void
    {
        $this->pickerOpen = true;
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
        $this->pickerSearch = '';
    }

    public function createCustom(): void
    {
        $label = trim($this->customLabel !== '' ? $this->customLabel : $this->pickerSearch);
        if ($label === '') {
            return;
        }
        abort_if(count($this->details) >= 50, 422);
        $row = $this->newRow(null);
        $row['label'] = mb_substr($label, 0, 120);
        $this->details[] = $row;
        $this->customLabel = $this->pickerSearch = '';
        $this->pickerOpen = false;
        $this->dispatch('composer-detail-added', index: count($this->details) - 1);
    }

    /** Offer a custom field to everyone. Only its name, kind, unit and choices are sent, never an answer. */
    public function suggestStandard(int $index): void
    {
        abort_unless(isset($this->details[$index]) && empty($this->details[$index]['canonical_key']), 404);
        app(ProposeIntentField::class)->execute($this->user(), $this->details[$index], app()->getLocale());
        $this->notice = __('composer.suggestion_sent');
    }

    public function toggleDetailPrivacy(int $index): void
    {
        abort_unless(isset($this->details[$index]), 404);
        $this->details[$index]['visibility'] = ($this->details[$index]['visibility'] ?? 'same') === 'private' ? 'same' : 'private';
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

    /**
     * Saves structure only, never answers or files. composition = fields + chosen terms + topic,
     * group = just the selected fields.
     */
    public function saveTemplate(string $kind = 'composition'): void
    {
        abort_unless(in_array($kind, ['composition', 'group'], true), 422);
        $name = trim($kind === 'group' ? $this->groupName : $this->templateName);
        $this->validate([($kind === 'group' ? 'groupName' : 'templateName') => ['required', 'string', 'max:120']]);
        $this->storeTemplate($kind, $name, $this->editableDetails(shape: true), $kind === 'composition' ? $this->terms : [], $kind === 'composition' ? $this->topic : '');
        $this->templateName = $this->groupName = '';
        $this->notice = __('composer.template_saved');
    }

    /** Keep one personal field (usually a custom one) to reuse in later compositions. */
    public function saveField(int $index): void
    {
        abort_unless(isset($this->details[$index]), 404);
        $row = $this->details[$index];
        $label = trim((string) ($row['label'] ?? ''));
        abort_if($label === '', 422);
        $this->storeTemplate('field', mb_substr($label, 0, 120), [$row], [], '');
        $this->notice = __('composer.field_saved');
    }

    /** @param list<array<string, mixed>> $rows @param list<string> $terms */
    private function storeTemplate(string $kind, string $name, array $rows, array $terms, string $topic): void
    {
        $publisher = $this->publisher();
        abort_unless(in_array($publisher->kind, [Party::ACTOR, Party::BUSINESS], true), 422);
        $shaped = app(NormalizeIntentDetails::class)->execute($this->user(), $rows, shapeOnly: true);
        $fields = array_map(function ($row): array {
            $options = $row['payload']['options'] ?? [];
            unset($row['payload']);

            return [...$row, 'options' => $options];
        }, $shaped);
        IntentDetailTemplate::query()->create(['owner_actor_id' => $publisher->kind === Party::ACTOR ? $publisher->id : null,
            'owner_business_id' => $publisher->kind === Party::BUSINESS ? $publisher->id : null, 'name' => $name, 'kind' => $kind,
            'topic' => $topic === '' ? null : $topic, 'terms' => $terms, 'fields' => $fields]);
    }

    private function ownedTemplate(string $uuid): IntentDetailTemplate
    {
        $publisher = $this->publisher();
        $template = IntentDetailTemplate::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless(($publisher->kind === Party::ACTOR && $template->owner_actor_id === $publisher->id)
            || ($publisher->kind === Party::BUSINESS && $template->owner_business_id === $publisher->id), 403);

        return $template;
    }

    /** Adds what is missing; answers already given are never touched. */
    public function applyTemplate(string $uuid): void
    {
        $template = $this->ownedTemplate($uuid);
        $added = 0;
        foreach ($template->fields as $field) {
            $key = $field['canonical_key'] ?? null;
            $exists = collect($this->details)->contains(fn (array $row): bool => $key !== null
                ? ($row['canonical_key'] ?? null) === $key
                : empty($row['canonical_key']) && ($row['label'] ?? '') === ($field['label'] ?? '') && ($row['type'] ?? '') === ($field['type'] ?? ''));
            if ($exists) {
                continue;
            }
            abort_if(count($this->details) >= 50, 422);
            $this->details[] = [...$field, 'editor_key' => (string) Str::uuid(), 'value' => in_array($field['type'], ['multiple_choice', 'date_range', 'reference'], true) ? [] : ($field['type'] === 'boolean' && $key !== null ? false : '')];
            $added++;
        }
        foreach ($template->terms ?? [] as $block) {
            if (in_array($block, IntentTerms::BLOCKS, true)) {
                $this->addTerm($block);
            }
        }
        if ($this->topic === '' && ! empty($template->topic) && in_array($template->topic, IntentProfiles::topics($this->intention), true)) {
            $this->chooseTopic($template->topic);
        }
        $this->pickerOpen = false;
        $this->notice = __('composer.template_applied', ['count' => $added]);
    }

    public function startRename(string $uuid): void
    {
        $this->renaming = $uuid;
        $this->renameTo = $this->ownedTemplate($uuid)->name;
    }

    public function saveRename(): void
    {
        $this->validate(['renameTo' => ['required', 'string', 'max:120']]);
        $this->ownedTemplate($this->renaming)->update(['name' => $this->renameTo]);
        $this->renaming = $this->renameTo = '';
    }

    public function deleteTemplate(string $uuid): void
    {
        $this->ownedTemplate($uuid)->delete();
        $this->notice = __('composer.template_deleted');
    }

    public function updatedDetails(mixed $value, ?string $key = null): void
    {
        if ($key !== null && preg_match('/^(\d+)\.(value|min|max)$/D', $key, $match)) {
            $this->checkRow((int) $match[1]);
        }
        if ($key !== null && preg_match('/^(\d+)\.optionsText$/D', $key, $match) && is_string($value)) {
            $this->details[(int) $match[1]]['options'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
        }
    }

    /** Checks a typed value as it is entered; sets an expected-input message or a structured reading to confirm. */
    private function checkRow(int $index): void
    {
        $row = $this->details[$index] ?? null;
        $spec = $row === null || empty($row['canonical_key']) ? null : (IntentDetailCatalog::all()[$row['canonical_key']] ?? null);
        $this->details[$index]['error'] = null;
        $this->details[$index]['suggestion'] = null;
        if ($spec === null || ! in_array($spec['type'], ['number', 'amount', 'year'], true)) {
            return;
        }
        $type = (string) $spec['type'];
        $unit = $spec['unit'] ?? null;
        $example = IntentDetailCatalog::example($row['canonical_key'], null, $this->topic) ?? '10';
        foreach (['value', 'min', 'max'] as $field) {
            $text = trim((string) ($row[$field] ?? ''));
            if ($text === '') {
                continue;
            }
            try {
                DetailValue::scalar($type, $text);
            } catch (ValidationException $exception) {
                $names = array_values(array_filter([$unit, IntentDetailCatalog::unitLabel($unit), $unit === null ? null : (string) __('composer.units.'.$unit, [], 'en')]));
                $parsed = $type === 'amount' ? null : ConstraintParser::parse($text, $names);
                if ($parsed !== null && $this->isNeedSide() && in_array($parsed['op'], DetailValue::operators($type, true), true)) {
                    $suffix = $unit === null ? '' : ' '.IntentDetailCatalog::unitLabel($unit);
                    $this->details[$index]['suggestion'] = [...$parsed, 'meaning' => DetailValue::describe($type, ['constraint' => $parsed]).$suffix];
                } else {
                    $this->details[$index]['error'] = $parsed !== null ? (string) __('composer.interpret_offer', ['example' => $example]) : (string) __('composer.interpret_text', ['example' => $example]);
                    if ($type === 'year') {
                        $this->details[$index]['error'] = (string) ($exception->errors()['details'][0] ?? __('composer.errors.year'));
                    }
                }

                return;
            }
        }
    }

    /** The person confirmed the structured reading of what they typed. */
    public function applySuggestion(int $index): void
    {
        $suggestion = $this->details[$index]['suggestion'] ?? null;
        abort_unless(is_array($suggestion) && $this->isNeedSide(), 422);
        $this->details[$index] = [...$this->details[$index], 'op' => $suggestion['op'], 'min' => $suggestion['min'] ?? '', 'max' => $suggestion['max'] ?? '',
            'value' => '', 'suggestion' => null, 'error' => null];
    }

    public function toggleNote(int $index): void
    {
        abort_unless(isset($this->details[$index]), 404);
        $this->details[$index]['showNote'] = ! ($this->details[$index]['showNote'] ?? false);
    }

    private function saveWorking(): void
    {
        $user = $this->user();
        if (trim((string) ($this->core['title'] ?? '')) === '' && trim((string) ($this->core['description'] ?? '')) === '') {
            throw ValidationException::withMessages(['core.description' => __('composer.describe_required')]);
        }
        $fromCatalog = $this->intent?->business_listing_version_id !== null;
        $priceMode = in_array('price', $this->terms, true) || $fromCatalog ? $this->priceMode : 'ask';
        $core = $this->scopedCore($this->core, $fromCatalog);
        $core['exchange_preference'] = match ($priceMode) {
            'free' => 'free', 'exchange' => 'open_hybrid', 'fixed', 'range', 'per_unit' => 'cash_only', default => 'discuss_later'
        };
        if ($fromCatalog) {
            $priceMode = 'ask';
        }
        $preserveLegacyPrice = $fromCatalog || ($this->hasLegacyPrice() && ! $this->replaceLegacyPrice);
        $core['cash_min'] = ! $preserveLegacyPrice && in_array($priceMode, ['fixed', 'range', 'per_unit'], true) ? (string) MarketMoney::parse($this->price, 'price') : null;
        $core['cash_max'] = ! $preserveLegacyPrice && $priceMode === 'range' ? (string) MarketMoney::parse($this->maximumPrice, 'maximumPrice') : $core['cash_min'];
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
            'profile' => $this->profileView(), 'needSide' => $this->isNeedSide(),
            'detailTypes' => NormalizeIntentDetails::TYPES, 'legacyPrice' => $this->hasLegacyPrice(),
            'publisher' => $this->publisher(),
            'versions' => $this->intent?->versions()->reorder('version', 'desc')->limit(50)->get() ?? collect(),
            'catalog' => $this->catalog(),
            'mediaItems' => $this->intent?->media()->with('asset')->get() ?? collect(),
            'referenceChoices' => $this->referenceChoices(),
            'presentation' => app(IntentPresentation::class)->build($this->previewSnapshot(), $this->previewAudience === 'owner' ? $user : null, $this->previewAudience === 'owner', $this->intent),
            'capabilities' => $this->intent === null ? Capability::query()->heldBy($this->publisher())->active()->with('concept.labels')->limit(12)->get() : collect(),
            'templates' => $this->savedTemplates('')])->title(__('composer.title'));
    }

    /** @return array<string, mixed> */
    private function answers(): array
    {
        $answers = [];
        foreach ($this->details as $row) {
            if (! empty($row['canonical_key'])) {
                $answers[$row['canonical_key']] = $row['value'] ?? null;
            }
        }

        return $answers;
    }

    /** @return list<string> */
    private function inapplicableKeys(): array
    {
        return IntentProfiles::inapplicable($this->topic, $this->intention, (string) ($this->core['arrangement_kind'] ?? 'other'), $this->answers());
    }

    /** @return array<string, mixed> */
    private function profileView(): array
    {
        $answers = $this->answers();
        $hint = $this->topicHint();
        // Recommendations may lean on the topic the words suggest, but nothing is chosen for the person.
        $lean = $this->topic !== '' ? $this->topic : (string) $hint;
        $arrangement = $this->topic !== '' ? (string) ($this->core['arrangement_kind'] ?? 'other') : IntentProfiles::arrangement($lean, (string) ($this->core['arrangement_kind'] ?? 'other'));
        $fields = IntentProfiles::fields($lean, $this->intention, $arrangement, $answers);
        $selected = array_filter(array_column($this->details, 'canonical_key'));
        $profile = IntentProfiles::all()[$this->topic] ?? null;
        $termsLeft = array_values(array_diff(IntentProfiles::terms($lean, $answers), $this->terms));
        $fieldsLeft = array_values(array_diff([...$fields['essential'], ...$fields['suggested']], $selected));

        return ['topics' => IntentProfiles::topics($this->intention),
            'arrangements' => count($profile['arrangements'] ?? []) > 1 ? $profile['arrangements'] : [],
            'recommendedFields' => $fieldsLeft, 'recommendedTerms' => $termsLeft,
            'inapplicable' => $this->inapplicableKeys(), 'hint' => $hint,
            'picker' => $this->pickerOpen ? $this->pickerView($fieldsLeft, $termsLeft, $selected) : null];
    }

    /** The topic whose words appear in what was written, if none is chosen yet. */
    private function topicHint(): ?string
    {
        $text = IntentFieldLibrary::normalize((string) ($this->core['title'] ?? '').' '.(string) ($this->core['description'] ?? ''));
        if ($this->topic !== '' || mb_strlen($text) < 3) {
            return null;
        }
        foreach (IntentProfiles::topics($this->intention) as $topic) {
            foreach (explode(',', (string) __('composer.topic_words.'.$topic)) as $word) {
                $word = IntentFieldLibrary::normalize($word);
                if ($word !== '' && preg_match('/(^|\s)'.preg_quote($word, '/').'/u', $text) === 1) {
                    return $topic;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fieldsLeft
     * @param  list<string>  $termsLeft
     * @param  array<int, string>  $selected
     * @return array<string, mixed>
     */
    private function pickerView(array $fieldsLeft, array $termsLeft, array $selected): array
    {
        $taken = array_values($selected);
        $query = trim($this->pickerSearch);
        $terms = array_values(array_diff(IntentTerms::BLOCKS, $this->terms));
        if ($query !== '') {
            $needle = IntentFieldLibrary::normalize($query);
            $terms = array_values(array_filter($terms, fn (string $block): bool => str_contains(IntentFieldLibrary::normalize((string) __('composer.terms.'.$block.'.label')), $needle)
                || str_contains(IntentFieldLibrary::normalize((string) __('composer.terms.'.$block.'.words')), $needle)));

            return ['query' => $query, 'terms' => $terms, 'matches' => IntentFieldLibrary::search($query, $taken),
                'saved' => $this->savedTemplates($query)];
        }

        return ['query' => '', 'terms' => array_values(array_diff($terms, $termsLeft)), 'recommendedFields' => array_slice($fieldsLeft, 0, 8), 'recommendedTerms' => $termsLeft,
            'recent' => array_values(array_diff($this->recentKeys(), $taken)), 'saved' => $this->savedTemplates(''),
            'groups' => IntentFieldLibrary::grouped($taken)];
    }

    /** @return list<string> */
    private function recentKeys(): array
    {
        $publisher = $this->publisher();
        $column = match ($publisher->kind) {
            Party::ACTOR => 'publisher_actor_id', Party::BUSINESS => 'publisher_business_id', default => 'publisher_group_id',
        };
        $keys = DB::table('intent_details')->join('actor_profile_intents', 'intent_details.actor_profile_intent_id', '=', 'actor_profile_intents.id')
            ->where('actor_profile_intents.'.$column, $publisher->id)->whereNotNull('intent_details.canonical_key')
            ->orderByDesc('intent_details.id')->limit(60)->pluck('intent_details.canonical_key')->unique()->values()->all();

        return array_slice(array_values(array_filter($keys, fn ($key): bool => isset(IntentDetailCatalog::all()[$key]))), 0, 8);
    }

    /** @return Collection<int, IntentDetailTemplate> */
    private function savedTemplates(string $query): Collection
    {
        $publisher = $this->publisher();
        if (! in_array($publisher->kind, [Party::ACTOR, Party::BUSINESS], true)) {
            return collect();
        }
        $templates = IntentDetailTemplate::query()->where($publisher->kind === Party::BUSINESS ? 'owner_business_id' : 'owner_actor_id', $publisher->id)
            ->orderBy('name')->get();
        $needle = IntentFieldLibrary::normalize($query);

        return $needle === '' ? $templates : $templates->filter(fn (IntentDetailTemplate $template): bool => str_contains(IntentFieldLibrary::normalize($template->name), $needle))->values();
    }

    private function hasLegacyPrice(): bool
    {
        return $this->intent !== null && $this->intent->currency_code !== MarketMoney::currency()
            && ($this->intent->cash_min !== null || $this->intent->cash_max !== null);
    }

    private function previewSnapshot(): array
    {
        $core = $this->scopedCore($this->core, $this->intent?->business_listing_version_id !== null);
        $priceMode = in_array('price', $this->terms, true) ? $this->priceMode : 'ask';
        if (! $this->hasLegacyPrice() || $this->replaceLegacyPrice) {
            $core['currency_code'] = MarketMoney::currency();
            $core['exchange_preference'] = $priceMode === 'free' ? 'free' : 'discuss_later';
            try {
                $core['cash_min'] = in_array($priceMode, ['fixed', 'range', 'per_unit'], true) ? (string) MarketMoney::parse($this->price) : null;
                $core['cash_max'] = $priceMode === 'range' ? (string) MarketMoney::parse($this->maximumPrice) : $core['cash_min'];
            } catch (ValidationException) {
                $core['cash_min'] = $core['cash_max'] = null;
            }
        }

        return ['core' => $core, 'details' => array_map(fn ($detail): array => [...$detail, 'payload' => $this->previewPayload($detail)], $this->details),
            'media' => $this->intent === null ? [] : $this->intent->media()->get(['uuid', 'visibility', 'caption'])->toArray()];
    }

    /** @param array<string, mixed> $detail @return array<string, mixed> */
    private function previewPayload(array $detail): array
    {
        $payload = ['value' => $detail['value'] ?? null];
        $op = $this->isNeedSide() && ! empty($detail['canonical_key']) ? ($detail['op'] ?? 'exact') : 'exact';
        if ($op !== 'exact') {
            try {
                $payload = ['value' => null, 'constraint' => DetailValue::constraint((string) $detail['type'], $op, $detail, $detail['options'] ?? [])];
            } catch (ValidationException) {
                return ['value' => null];
            }
        }
        if (trim((string) ($detail['note'] ?? '')) !== '') {
            $payload['note'] = trim((string) $detail['note']);
        }
        if (($detail['type'] ?? '') === 'year' && ! DetailValue::blank($detail['value'] ?? ($detail['min'] ?? null))) {
            $payload['calendar'] = YearSpan::guessCalendar((int) ($detail['value'] ?: ($detail['min'] ?: $detail['max'])));
        }

        return $payload;
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
        $skip = $shape ? [] : $this->inapplicableKeys();
        $answered = $shape ? $this->details : array_filter($this->details, fn (array $detail): bool => self::answered($detail)
            && ! in_array($detail['canonical_key'] ?? null, $skip, true));

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
