<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops a custom-named foreign key on every supported database.
 *
 * MySQL/MariaDB need the real constraint name; SQLite has no named
 * constraints and Laravel locates its foreign keys by column instead.
 */
final class PortableForeignKey
{
    /** @param  list<string>  $columns */
    public static function drop(Blueprint $table, string $name, array $columns): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $table->dropForeign($columns);

            return;
        }

        $table->dropForeign($name);
    }
}
