<?php

namespace App\Support\Market;

use App\CalendarSystem;
use App\Models\ActorProfileIntent;
use App\Models\Asset;
use App\Models\BusinessListing;
use App\Models\Group;
use App\Models\IntentVersion;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\TemporalPreferences;
use Illuminate\Support\Facades\Gate;

/** One audience-aware view model for a post, its preview and its historical versions. */
final class IntentPresentation
{
    public function published(ActorProfileIntent $intent, ?User $viewer): array
    {
        Gate::forUser($viewer)->authorize('view', $intent);
        $version = $intent->publishedVersion()->firstOrFail();

        return $this->build($version->snapshot, $viewer, Gate::forUser($viewer)->allows('update', $intent), $intent);
    }

    /** The caller authorizes access to the source; this method only reduces it for a viewer. */
    /** Presentation of an already-published version, for lists where visibility was applied by the query. */
    public function forVersion(ActorProfileIntent $intent, ?User $viewer, string $category = ''): array
    {
        $version = $intent->publishedVersion;
        $snapshot = $version instanceof IntentVersion ? $version->snapshot : IntentSnapshot::fromRecord((object) $intent->getAttributes());
        $card = $this->build($snapshot, $viewer, false, $intent);
        $card['category'] = $category !== '' ? $category : (string) $intent->concept?->displayLabel();
        $core = $snapshot['core'] ?? [];
        if ($card['price'] === null && isset($core['cash_min']) && ! in_array($core['currency_code'] ?? null, ['IRT', 'IRR', null], true)) {
            // The directory keeps showing a legacy amount in another currency as it was written, never converted.
            $plain = fn ($amount): string => str_contains((string) $amount, '.') ? rtrim(rtrim((string) $amount, '0'), '.') : (string) $amount;
            $card['price'] = $plain($core['cash_min']).(isset($core['cash_max']) && $core['cash_max'] != $core['cash_min'] ? ' – '.$plain($core['cash_max']) : '').' '.$core['currency_code'];
        }

        return $card;
    }

    public function build(array $snapshot, ?User $viewer, bool $canManage = false, ?ActorProfileIntent $intent = null, ?string $kind = null, ?string $topic = null): array
    {
        $core = $snapshot['core'] ?? [];
        $details = [];
        $topic ??= $intent?->metadata['composer_topic'] ?? null;
        $kind ??= $intent?->kind->value;
        $viewCalendar = TemporalPreferences::calendarFor($viewer) === CalendarSystem::Persian ? YearSpan::PERSIAN : YearSpan::GREGORIAN;
        foreach ($snapshot['details'] ?? [] as $detail) {
            if (($detail['visibility'] ?? 'same') === 'private' && ! $canManage) {
                continue;
            }
            $payload = $detail['payload'] ?? ['value' => $detail['value'] ?? null];
            $value = $payload['value'] ?? null;
            $constraint = $payload['constraint'] ?? null;
            if (($value === null || $value === '' || $value === []) && $constraint === null) {
                continue;
            }
            $url = null;
            $type = $detail['type'] ?? 'text';
            if ($constraint !== null || (! empty($detail['canonical_key']) && in_array($type, ['number', 'amount', 'year', 'choice'], true))) {
                $value = DetailValue::describe($type, $payload, $viewCalendar);
            } elseif ($type === 'reference') {
                $reference = $this->reference(is_array($value) ? $value : [], $viewer);
                if ($reference === null) {
                    continue;
                }
                [$value, $url] = $reference;
            } elseif ($type === 'link') {
                if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                    continue;
                }
                $url = $value;
                $value = parse_url($value, PHP_URL_HOST);
            } elseif ($type === 'choice' && is_string($value)) {
                $value = IntentDetailCatalog::optionLabel($value);
            } elseif ($type === 'multiple_choice' && is_array($value)) {
                $value = implode(', ', array_map(fn ($option): string => IntentDetailCatalog::optionLabel((string) $option), $value));
            } elseif ($type === 'boolean') {
                $value = $value ? __('composer.yes') : __('composer.no');
            } elseif ($type === 'amount' && is_numeric($value)) {
                $value = MarketMoney::format((string) $value);
            } elseif ($type === 'date_range' && is_array($value)) {
                $value = ($value['start'] ?? '').' – '.($value['end'] ?? '');
            } elseif (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }
            $details[] = ['key' => $detail['canonical_key'] ?? null, 'note' => $payload['note'] ?? null, 'wish' => $constraint !== null,
                'label' => ! empty($detail['canonical_key']) ? IntentDetailCatalog::label($detail['canonical_key'], null, $topic) : ($detail['label'] ?? ''), 'value' => (string) $value, 'url' => $url,
                'unit' => $type === 'amount' ? null : IntentDetailCatalog::unitLabel($detail['unit'] ?? null), 'group' => $detail['group_label'] ?? null,
                'private' => ($detail['visibility'] ?? 'same') === 'private', 'informational' => $type === 'amount'];
        }
        $minimum = MarketMoney::legacyToman(isset($core['cash_min']) ? (string) $core['cash_min'] : null, $core['currency_code'] ?? null);
        $maximum = MarketMoney::legacyToman(isset($core['cash_max']) ? (string) $core['cash_max'] : null, $core['currency_code'] ?? null);
        $price = $minimum === null ? null : MarketMoney::format($minimum);
        if ($maximum !== null && $maximum !== $minimum) {
            $price = ($price === null ? '' : $price.' – ').MarketMoney::format($maximum);
        }
        if (($core['exchange_preference'] ?? '') === 'free') {
            $price = __('composer.prices.free');
        }
        $media = [];
        if ($intent !== null) {
            $current = $intent->media()->with('asset')->get()->keyBy('uuid');
            foreach ($snapshot['media'] ?? [] as $item) {
                $row = $current->get($item['uuid']);
                if ($row === null || (($item['visibility'] ?? 'same') === 'private' || $row->visibility === 'private') && ! $canManage) {
                    continue;
                }
                $asset = $row->asset;
                if (! $asset instanceof Asset || ! $asset->isPublishable() || ! $asset->isReadyForPublication()) {
                    continue;
                }
                $media[] = ['url' => route('intents.media.show', [$intent, $row]), 'caption' => $item['caption'] ?? '',
                    'mime' => $asset->mime_type, 'name' => $asset->original_filename];
            }
        }

        return ['title' => $core['title'] ?? '', 'description' => $core['description'] ?? '', 'details' => $details,
            'price' => $price, 'basis' => $core['cash_basis'] ?? null, 'location' => $core['location_text'] ?? null,
            'quantity' => $core['quantity'] ?? null, 'unit' => $core['unit'] ?? null,
            'starts_on' => $core['starts_on'] ?? null, 'ends_on' => $core['ends_on'] ?? null, 'media' => $media,
            'kind' => $kind, 'topic' => $topic ?: null, 'topicLabel' => $topic ? (string) __('composer.topics.'.$topic) : null,
            'arrangement' => $kind !== null && in_array($core['arrangement_kind'] ?? '', ['ownership_transfer', 'temporary_use'], true) ? (string) __('composer.arrangements.'.$core['arrangement_kind'].'.'.$kind) : null,
            'priceLabel' => (string) __('composer.terms.price.'.($kind === 'need' ? 'need' : 'label')), 'placeLabel' => (string) __('composer.terms.place.'.($kind === 'need' ? 'need' : 'label')),
            'highlightKeys' => IntentProfiles::highlights((string) $topic), 'negotiable' => (bool) ($core['negotiable'] ?? false),
            'route' => ! empty($core['origin_text']) || ! empty($core['destination_text']) ? trim(($core['origin_text'] ?? '').' → '.($core['destination_text'] ?? ''), ' →') : null,
            'category' => '', 'owner' => null];
    }

    private function reference(array $value, ?User $viewer): ?array
    {
        $record = match ($value['type'] ?? '') {
            'content' => SpaceContent::query()->find($value['id'] ?? null),
            'group' => Group::query()->find($value['id'] ?? null),
            'catalog' => BusinessListing::query()->find($value['id'] ?? null),
            default => null,
        };
        if ($record === null || ! Gate::forUser($viewer)->allows('view', $record)) {
            return null;
        }
        if ($record instanceof Group) {
            return [$record->name, route('groups.show', $record)];
        }
        if ($record instanceof BusinessListing) {
            return [$record->publishedVersion()->value('title') ?? $record->uuid,
                route('public.businesses.listings.show', [$record->business, $record])];
        }

        return [$record->activeRevision()->value('title') ?? $record->uuid,
            $viewer === null ? route('public-content.show', $record) : route('contexts.contents.show', [$record->context, $record])];
    }
}
