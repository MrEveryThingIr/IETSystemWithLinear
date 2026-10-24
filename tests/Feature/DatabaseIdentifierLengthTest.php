<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** MariaDB/MySQL reject identifiers longer than 64 characters; SQLite does not, so check here. */
class DatabaseIdentifierLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_table_and_index_name_fits_mariadb(): void
    {
        $tooLong = collect(DB::select("select name from sqlite_master where type in ('table', 'index') and name not like 'sqlite_%'"))
            ->pluck('name')
            ->filter(fn (string $name): bool => strlen($name) > 64)
            ->values()
            ->all();

        $this->assertSame([], $tooLong);
    }
}
