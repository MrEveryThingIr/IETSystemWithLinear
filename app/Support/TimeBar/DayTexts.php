<?php

namespace App\Support\TimeBar;

use App\Models\DayText;
use App\Models\FeatureSurfaceSetting;
use App\Models\User;
use App\Support\RequestMemo;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Chooses what the moving "text of the day" line shows.
 *
 * Dated entries are announcements: each is shown on every day of its range.
 * Undated entries form a rotation: one of them per day, in order. When
 * nothing is chosen the built-in message is shown, unless the superadmin
 * turned it off. Everything is read through one cache entry, so the bar on
 * every page costs a single cache read.
 */
final class DayTexts
{
    public const CACHE_KEY = 'time-bar:day-texts:v1';

    public const SHOW_DEFAULT_SETTING = 'time_bar.show_default_text';

    /** @return list<array{kind: string, text: string, attribution: ?string, url: ?string}> */
    public static function today(?User $user, string $locale, ?CarbonImmutable $now = null): array
    {
        $state = self::state();
        $today = ($now ?? CarbonImmutable::now(TemporalPreferences::timezoneFor($user)))->toDateString();

        $scheduled = [];
        $rotation = [];
        foreach ($state['rows'] as $row) {
            if ($row['starts_on'] === null && $row['ends_on'] === null) {
                $rotation[] = $row;
            } elseif (($row['starts_on'] === null || $row['starts_on'] <= $today) && ($row['ends_on'] === null || $row['ends_on'] >= $today)) {
                $scheduled[] = $row;
            }
        }

        $chosen = $scheduled;
        if ($rotation !== []) {
            // Same pick for everyone all day; the next one tomorrow.
            $day = intdiv(CarbonImmutable::parse($today, 'UTC')->getTimestamp(), 86400);
            $chosen[] = $rotation[$day % count($rotation)];
        }

        $items = [];
        foreach ($chosen as $row) {
            $text = self::pick($row['texts'], $locale);
            if ($text !== '') {
                $items[] = ['kind' => $row['kind'], 'text' => $text, 'attribution' => $row['attribution'], 'url' => $row['url']];
            }
        }

        if ($items === [] && $state['show_default']) {
            $items[] = ['kind' => 'motto', 'text' => self::defaultText($locale), 'attribution' => null, 'url' => null];
        }

        return $items;
    }

    public static function defaultText(string $locale): string
    {
        return (string) __('time_bar.default_text', [], $locale);
    }

    public static function showsDefault(): bool
    {
        return self::state()['show_default'];
    }

    public static function setShowDefault(bool $show): void
    {
        FeatureSurfaceSetting::setValue(self::SHOW_DEFAULT_SETTING, $show ? '1' : '0');
        self::forget();
    }

    /** @param  array<string, mixed>  $texts */
    public static function pick(array $texts, string $locale): string
    {
        foreach ([$locale, 'en'] as $candidate) {
            $value = trim((string) ($texts[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        foreach ($texts as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    public static function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $exception) {
            report($exception);
        }
        RequestMemo::flush();
    }

    /** @return array{rows: list<array{kind: string, texts: array<string, string>, attribution: ?string, url: ?string, starts_on: ?string, ends_on: ?string}>, show_default: bool} */
    private static function state(): array
    {
        return RequestMemo::remember('time-bar:day-texts', function (): array {
            // The bar is on every page, including during an upgrade before
            // migrations ran: it must never take a page down.
            try {
                return Cache::remember(self::CACHE_KEY, 600, fn (): array => [
                    'rows' => DayText::query()
                        ->where('active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->limit(200)
                        ->get()
                        ->map(fn (DayText $text): array => [
                            'kind' => $text->kind,
                            'texts' => array_filter(array_map(fn ($v) => is_string($v) ? $v : '', $text->texts ?? [])),
                            'attribution' => $text->attribution,
                            'url' => $text->link_url,
                            'starts_on' => $text->starts_on?->toDateString(),
                            'ends_on' => $text->ends_on?->toDateString(),
                        ])
                        ->values()
                        ->all(),
                    'show_default' => FeatureSurfaceSetting::getValue(self::SHOW_DEFAULT_SETTING, '1') !== '0',
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return ['rows' => [], 'show_default' => true];
            }
        });
    }
}
