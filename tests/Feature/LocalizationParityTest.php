<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationParityTest extends TestCase
{
    public function test_persian_locale_has_every_english_translation_file_and_real_key(): void
    {
        $englishFiles = collect(glob(base_path('lang/en/*.php')))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values();

        $persianFiles = collect(glob(base_path('lang/fa/*.php')))
            ->map(fn (string $path): string => basename($path))
            ->sort()
            ->values();

        $this->assertSame(
            $englishFiles->all(),
            $persianFiles->all(),
            'Persian locale files must stay in exact file parity with English.',
        );

        $missing = [];

        foreach ($englishFiles as $file) {
            $english = Arr::dot(require base_path('lang/en/'.$file));
            $persian = Arr::dot(require base_path('lang/fa/'.$file));

            foreach ($english as $key => $value) {
                if (is_array($value)) {
                    continue;
                }

                if ($file === 'validation.php' && $key === 'custom.attribute-name.rule-name') {
                    continue;
                }

                if (! array_key_exists($key, $persian)) {
                    $missing[$file][] = $key;
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Persian is missing English translation keys: '.json_encode($missing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function test_persian_locale_never_passthroughs_to_english_files(): void
    {
        foreach (glob(base_path('lang/fa/*.php')) as $path) {
            $contents = file_get_contents($path);

            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                '../en/',
                $contents,
                basename($path).' must contain real Persian copy instead of loading English translations.',
            );
        }
    }
}
