<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
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
}
