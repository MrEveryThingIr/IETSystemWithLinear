<?php

namespace Tests\Feature\Ui;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class UiShellHygieneTest extends TestCase
{
    public function test_blade_sources_do_not_contain_transitional_shell_debris(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            $this->assertIsString($source, $path);

            $this->assertStringNotContainsString('<x-layouts.app', $source, "Transitional layout component remains in {$path}");
            $this->assertStringNotContainsString('</x-layouts.app>', $source, "Transitional layout component remains in {$path}");
            $this->assertStringNotContainsString('workspace.partials.chrome', $source, "Obsolete nested workspace chrome remains in {$path}");
            $this->assertDoesNotMatchRegularExpression(
                '/@section\([\'\"]content[\'\"]\)\s*(?:getLocale\(\)|name[\'\"]>|\]\))/s',
                $source,
                "Visible shell-conversion debris remains in {$path}"
            );
        }
    }

    public function test_authenticated_milestone_views_use_the_canonical_layout(): void
    {
        $paths = [
            resource_path('views/profile/contact-center/index.blade.php'),
            resource_path('views/profile/professions/index.blade.php'),
            resource_path('views/businesses/index.blade.php'),
            resource_path('views/businesses/create.blade.php'),
            resource_path('views/businesses/show.blade.php'),
            resource_path('views/workspace/real-estate.blade.php'),
            resource_path('views/public-intake/real-estate/admin/index.blade.php'),
            resource_path('views/public-intake/real-estate/admin/show.blade.php'),
            resource_path('views/admin/surfaces/index.blade.php'),
            resource_path('views/admin/surfaces/edit.blade.php'),
        ];

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertIsString($source, $path);
            $this->assertStringContainsString("@extends('layouts.app')", $source, "Canonical app layout missing from {$path}");
        }
    }
}
