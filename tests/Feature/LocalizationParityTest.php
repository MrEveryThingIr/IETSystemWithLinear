<?php

namespace Tests\Feature;

use App\Support\Localization;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class LocalizationParityTest extends TestCase
{
    public function test_supported_locales_cover_every_english_translation_file_and_real_key(): void
    {
        $englishFiles = collect(glob(base_path('lang/en/*.php')))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values();

        $missing = [];

        foreach (['fa', 'ar', 'zh_CN'] as $locale) {
            $localeFiles = collect(glob(base_path("lang/{$locale}/*.php")))
                ->map(fn (string $path): string => basename($path))
                ->sort()
                ->values();

            $fileGap = array_values(array_diff($englishFiles->all(), $localeFiles->all()));

            if ($fileGap !== []) {
                $missing[$locale]['files'] = $fileGap;
            }

            foreach ($englishFiles as $file) {
                $localePath = base_path("lang/{$locale}/{$file}");

                if (! is_file($localePath)) {
                    continue;
                }

                $english = Arr::dot(require base_path('lang/en/'.$file));
                $translated = Arr::dot(require $localePath);

                foreach ($english as $key => $value) {
                    if (is_array($value)) {
                        continue;
                    }

                    if ($file === 'validation.php' && $key === 'custom.attribute-name.rule-name') {
                        continue;
                    }

                    if (! array_key_exists($key, $translated)) {
                        $missing[$locale][$file][] = $key;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Supported locales are missing English translation coverage: '.json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function test_supported_locales_preserve_translation_placeholders(): void
    {
        $mismatches = [];

        foreach (glob(base_path('lang/en/*.php')) as $englishPath) {
            $file = basename($englishPath);
            $english = Arr::dot(require $englishPath);

            foreach (['fa', 'ar', 'zh_CN'] as $locale) {
                $localePath = base_path("lang/{$locale}/{$file}");

                if (! is_file($localePath)) {
                    continue;
                }

                $translated = Arr::dot(require $localePath);

                foreach ($english as $key => $englishValue) {
                    if (! is_string($englishValue) || ! isset($translated[$key]) || ! is_string($translated[$key])) {
                        continue;
                    }

                    $englishPlaceholders = $this->translationPlaceholders($englishValue);
                    $translatedPlaceholders = $this->translationPlaceholders($translated[$key]);

                    if ($englishPlaceholders !== $translatedPlaceholders) {
                        $mismatches[$locale][$file][$key] = [
                            'english' => $englishPlaceholders,
                            'translated' => $translatedPlaceholders,
                        ];
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $mismatches,
            'Localized placeholders do not match English: '.json_encode($mismatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function test_supported_locales_never_passthrough_to_english_files(): void
    {
        foreach (['fa', 'ar', 'zh_CN'] as $locale) {
            foreach (glob(base_path("lang/{$locale}/*.php")) as $path) {
                $contents = file_get_contents($path);

                $this->assertIsString($contents);
                $this->assertStringNotContainsString(
                    '../en/',
                    $contents,
                    $locale.'/'.basename($path).' must contain localized copy instead of loading English translations.',
                );
            }
        }
    }

    public function test_static_ui_translation_keys_resolve_in_every_supported_locale(): void
    {
        $missing = [];
        $roots = [
            resource_path('views'),
            app_path('Livewire'),
            app_path('View'),
        ];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if (! in_array($file->getExtension(), ['php'], true)) {
                    continue;
                }

                $contents = $file->getContents();
                preg_match_all(
                    '/(?:(?:__|trans|trans_choice)\\(\\s*|@lang\\(\\s*)[\'"]([^\'"]+)[\'"]/',
                    $contents,
                    $matches,
                );

                foreach (array_unique($matches[1] ?? []) as $key) {
                    foreach (Localization::codes() as $locale) {
                        if (! Lang::has($key, $locale, false)) {
                            $missing[$locale][$file->getRelativePathname()][] = $key;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Static UI translation keys are missing in one or more locales: '.json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function test_blade_templates_do_not_escape_server_side_php_interpolation(): void
    {
        $leaks = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = $file->getContents();

            if (preg_match_all('/@\\{\\{\\s*\\$[A-Za-z_]/', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $leaks[$file->getRelativePathname()][] = $line;
            }
        }

        $this->assertSame(
            [],
            $leaks,
            'Blade templates contain escaped server-side expressions that would render literally: '.json_encode($leaks, JSON_PRETTY_PRINT),
        );
    }

    public function test_primary_ui_templates_do_not_ship_literal_english_control_text(): void
    {
        $literals = [];
        $roots = [
            resource_path('views/livewire'),
            resource_path('views/components/app'),
            resource_path('views/layouts'),
        ];

        foreach ($roots as $root) {
            foreach (File::allFiles($root) as $file) {
                if (! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                foreach (preg_split('/\R/', $file->getContents()) ?: [] as $index => $line) {
                    if (preg_match('/>[ \t]*[A-Z][A-Za-z][^<{]{2,}[ \t]*</', trim($line), $match) !== 1) {
                        continue;
                    }

                    $literals[$file->getRelativePathname()][] = [
                        'line' => $index + 1,
                        'text' => trim($match[0]),
                    ];
                }
            }
        }

        $this->assertSame(
            [],
            $literals,
            'Primary UI templates contain literal English control text: '.json_encode($literals, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function test_blade_templates_do_not_ship_hardcoded_english_aria_labels(): void
    {
        $labels = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = $file->getContents();
            preg_match_all('/(?<!:)aria-label="([A-Za-z][^"]*)"/', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] ?? [] as [$label, $offset]) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $labels[$file->getRelativePathname()][] = [
                    'line' => $line,
                    'label' => $label,
                ];
            }
        }

        $this->assertSame(
            [],
            $labels,
            'Blade templates contain hardcoded English aria-label text: '.json_encode($labels, JSON_PRETTY_PRINT),
        );
    }

    /**
     * @return list<string>
     */
    private function translationPlaceholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);

        $placeholders = array_values(array_unique($matches[0] ?? []));
        sort($placeholders);

        return $placeholders;
    }
}
