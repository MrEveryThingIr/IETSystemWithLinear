<?php

namespace Tests\Feature\Businesses;

use Tests\TestCase;

class BusinessReleaseLocalizationHygieneTest extends TestCase
{
    public function test_business_and_real_estate_release_views_do_not_embed_language_specific_copy(): void
    {
        foreach ($this->releaseViews() as $path) {
            $contents = file_get_contents(base_path($path));

            $this->assertIsString($contents, "Could not read [{$path}].");
            $this->assertStringNotContainsString(
                '$fa',
                $contents,
                "[{$path}] must use translation keys instead of binary Persian/English branching.",
            );
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{0600}-\x{06FF}]/u',
                $contents,
                "[{$path}] contains hard-coded Persian/Arabic-script UI copy. Move it to lang/*.",
            );
        }
    }

    /** @return list<string> */
    private function releaseViews(): array
    {
        return [
            'resources/views/businesses/create.blade.php',
            'resources/views/businesses/index.blade.php',
            'resources/views/businesses/show.blade.php',
            'resources/views/businesses/catalog/index.blade.php',
            'resources/views/businesses/catalog/edit.blade.php',
            'resources/views/businesses/catalog/preview.blade.php',
            'resources/views/businesses/clients/index.blade.php',
            'resources/views/public-business/show.blade.php',
            'resources/views/public-business/listing.blade.php',
            'resources/views/public-intake/real-estate/show.blade.php',
            'resources/views/public-intake/real-estate/preview.blade.php',
            'resources/views/public-intake/real-estate/partials/media-fields.blade.php',
            'resources/views/public-intake/real-estate/admin/index.blade.php',
            'resources/views/public-intake/real-estate/admin/show.blade.php',
            'resources/views/public-intake/real-estate/admin/partials/media.blade.php',
        ];
    }
}
