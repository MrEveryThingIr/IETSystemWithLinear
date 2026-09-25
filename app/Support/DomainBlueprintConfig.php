<?php

namespace App\Support;

use App\DomainJourneyKind;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DomainBlueprintConfig
{
    /** @var list<string> */
    private const CAPABILITIES = [
        'conversation',
        'content',
        'planner',
        'proposal',
        'contract',
        'commitment',
        'fulfillment',
        'financial',
        'accounting',
    ];

    /** @return array{journey_kind:string,terminology:array<string,string>,capabilities:list<string>,content_blueprint_slugs:list<string>,guided_entry:array<string,mixed>,content_hash:string} */
    public function normalize(
        string|DomainJourneyKind $journeyKind,
        array $terminology,
        array $capabilities,
        array $contentBlueprintSlugs,
        array $guidedEntry,
    ): array {
        $journey = $journeyKind instanceof DomainJourneyKind
            ? $journeyKind
            : DomainJourneyKind::from($journeyKind);

        $terminology = collect($terminology)
            ->mapWithKeys(function (mixed $value, mixed $key): array {
                $key = Str::snake(trim((string) $key));
                $value = Str::squish((string) $value);

                abort_if($key === '' || mb_strlen($key) > 80, 422, 'Domain Blueprint terminology key is invalid.');
                abort_if($value === '' || mb_strlen($value) > 180, 422, 'Domain Blueprint terminology value is invalid.');

                return [$key => $value];
            })
            ->sortKeys()
            ->all();

        $capabilities = collect($capabilities)
            ->map(fn (mixed $item): string => Str::snake(trim((string) $item)))
            ->filter()
            ->unique()
            ->values();

        abort_if(
            $capabilities->contains(fn (string $capability): bool => ! in_array($capability, self::CAPABILITIES, true)),
            422,
            'Domain Blueprint contains an unsupported capability.',
        );

        $contentBlueprintSlugs = collect($contentBlueprintSlugs)
            ->map(fn (mixed $item): string => Str::slug((string) $item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $guidedEntry = Arr::sortRecursive($guidedEntry);

        $normalized = [
            'journey_kind' => $journey->value,
            'terminology' => $terminology,
            'capabilities' => $capabilities->sort()->values()->all(),
            'content_blueprint_slugs' => $contentBlueprintSlugs,
            'guided_entry' => $guidedEntry,
        ];

        return [
            ...$normalized,
            'content_hash' => hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR)),
        ];
    }
}
