<?php

namespace Tests\Feature;

use App\Support\Localization;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContentLocaleCatalogTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function catalogProvider(): array
    {
        return [
            'interactions' => ['interactions'],
            'presentation' => ['presentation'],
            'blocks' => ['blocks'],
        ];
    }

    #[DataProvider('catalogProvider')]
    public function test_rich_content_catalogs_have_locale_key_parity(string $catalog): void
    {
        $englishKeys = array_keys(Arr::dot(require lang_path('en/'.$catalog.'.php')));
        sort($englishKeys);

        foreach (Localization::codes() as $code) {
            if ($code === 'en') {
                continue;
            }

            $path = lang_path($code.'/'.$catalog.'.php');
            $this->assertFileExists($path, "The [{$code}] locale must provide [{$catalog}.php].");
            $localeKeys = array_keys(Arr::dot(require $path));
            sort($localeKeys);

            $this->assertSame(
                $englishKeys,
                $localeKeys,
                "The [{$code}] [{$catalog}] catalog must match the English key structure.",
            );
        }
    }
}
