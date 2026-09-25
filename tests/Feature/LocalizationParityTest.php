<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationParityTest extends TestCase
{
    public function test_persian_locale_has_every_english_translation_file_and_key(): void
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
            'Persian locale files must stay in exact parity with English.',
        );

        foreach ($englishFiles as $file) {
            $english = require base_path('lang/en/'.$file);
            $persian = require base_path('lang/fa/'.$file);

            $englishKeys = array_keys(Arr::dot($english));
            $persianKeys = array_keys(Arr::dot($persian));

            sort($englishKeys);
            sort($persianKeys);

            $this->assertSame(
                $englishKeys,
                $persianKeys,
                'Persian translation keys are incomplete or stale in '.$file.'.',
            );
        }
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
