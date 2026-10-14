<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards against the shared-host MySQL/MariaDB failures found during the first
 * production deployment:
 *  - "Specified key was too long; max key length is 1000 bytes" (MyISAM default engine)
 *  - "Invalid default value for '<column>'" (legacy implicit TIMESTAMP defaults)
 */
class DatabasePortabilityTest extends TestCase
{
    /**
     * Framework-owned migrations and bookkeeping columns that keep TIMESTAMP:
     * nullable or with an explicit useCurrent() default, never implicit.
     *
     * @var list<string>
     */
    private const ALLOWED_TIMESTAMP_DECLARATIONS = [
        "\$table->timestamp('created_at')->useCurrent()",
    ];

    public function test_mysql_and_mariadb_connections_default_to_innodb(): void
    {
        $config = $this->databaseConfig();

        foreach (['mysql', 'mariadb'] as $connection) {
            $this->assertSame(
                "env('DB_ENGINE', 'InnoDB')",
                $config[$connection] ?? null,
                "The {$connection} connection must create tables with InnoDB unless DB_ENGINE overrides it.",
            );
        }
    }

    public function test_production_environment_template_pins_innodb_and_shared_host_media_mode(): void
    {
        $template = (string) file_get_contents($this->root().'/deploy/production.env.example');

        foreach ([
            'DB_ENGINE=InnoDB',
            'QUEUE_CONNECTION=sync',
            'BROADCAST_CONNECTION=log',
            'MEDIA_SCANNER_ENABLED=false',
            'MEDIA_SCANNER_REQUIRED=false',
        ] as $line) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($line, '/').'$/m', $template, "Missing [{$line}].");
        }

        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $template, 'The template must never contain a real APP_KEY.');
    }

    #[DataProvider('applicationMigrations')]
    public function test_application_migrations_do_not_rely_on_implicit_timestamp_defaults(string $path): void
    {
        $source = (string) file_get_contents($path);
        $source = str_replace(self::ALLOWED_TIMESTAMP_DECLARATIONS, '', $source);

        $this->assertDoesNotMatchRegularExpression(
            '/->timestamp(Tz)?\(/',
            $source,
            basename($path).' declares an explicit TIMESTAMP column. Use dateTime()/dateTimeTz() for application-controlled times; '
                .'legacy MySQL/MariaDB TIMESTAMP defaults break fresh installs ("Invalid default value").',
        );
    }

    public function test_known_host_failures_are_canonically_corrected(): void
    {
        $expectations = [
            '2026_09_24_060000_create_contract_version_kernel.php' => ['effective_from', 'effective_until', 'proposed_at', 'accepted_at', 'activated_at', 'superseded_at'],
            '2026_09_25_000000_create_commitment_fulfillment_kernel.php' => ['due_start_at', 'due_end_at', 'actual_start_at', 'actual_end_at', 'submitted_at', 'reviewed_at', 'opened_at', 'resolved_at'],
        ];

        foreach ($expectations as $file => $columns) {
            $source = (string) file_get_contents($this->root().'/database/migrations/'.$file);

            foreach ($columns as $column) {
                $this->assertStringContainsString("->dateTime('{$column}')", $source, "{$file}: {$column} must be DATETIME.");
                $this->assertStringNotContainsString("->timestamp('{$column}')", $source);
            }

            $this->assertDoesNotMatchRegularExpression('/->dateTime\([^)]*\)->useCurrent\(\)/', $source, "{$file}: domain times must not get artificial defaults.");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function applicationMigrations(): iterable
    {
        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];

        foreach ($files as $file) {
            // 0001_01_01_* are Laravel's own users/cache/jobs migrations (nullable or useCurrent timestamps).
            if (str_starts_with(basename($file), '0001_01_01_')) {
                continue;
            }

            yield basename($file) => [$file];
        }
    }

    /** @return array<string, string> raw engine expression per connection */
    private function databaseConfig(): array
    {
        $source = (string) file_get_contents($this->root().'/config/database.php');
        preg_match_all("/'(mysql|mariadb)' => \[(.*?)\n        \],/s", $source, $matches, PREG_SET_ORDER);

        $engines = [];
        foreach ($matches as [, $connection, $body]) {
            if (preg_match("/'engine' => (.+),/", $body, $engine) === 1) {
                $engines[$connection] = trim($engine[1]);
            }
        }

        return $engines;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
