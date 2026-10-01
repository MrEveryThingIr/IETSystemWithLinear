<?php

namespace App\Console\Commands;

use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Services\Surfaces\FeatureSurfaceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class AuditFeatureSurfaces extends Command
{
    protected $signature = 'iet:surface-audit';

    protected $description = 'Audit discoverable surfaces, dependencies, mapped routes and possible route redundancy.';

    public function handle(FeatureSurfaceRegistry $registry, FeatureSurfaceAccess $access): int
    {
        $this->info('IET surface baseline audit');
        $this->line('Enforcement: '.($access->strict() ? 'STRICT' : 'OBSERVE'));
        $rows = [];
        foreach ($registry->definitions() as $key => $surface) {
            $entry = $registry->entryRoute($key);
            $rows[] = [$key, $entry ? 'yes' : 'no', $entry ?? '-', implode(', ', $surface['dependencies']) ?: '-'];
        }
        $this->table(['Surface', 'Available', 'Entry route', 'Dependencies'], $rows);
        $seen = [];
        $duplicates = [];
        $unmapped = [];
        $ignored = [
            'boost.',
            '__flux.',
            'default-livewire.',
            'livewire.',
            'sanctum.',
            'ignition.',
            'storage.',
            'up',
            'login',
            'logout',
            'register',
            'password.',
            'verification.',
            'access-invitations.show',
            'access-invitations.register',
            'invitations.',
            'locale.update',
            'dashboard',
            'getting-started',
            'public.real-estate.',
            'admin.surfaces.',
            'workspace.index',
        ];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            $methods = implode('|', array_diff($route->methods(), ['HEAD']));
            $sig = $methods.' '.$route->uri();
            if (isset($seen[$sig]) && $seen[$sig] !== $name) {
                $duplicates[] = [$sig, $seen[$sig] ?: '-', $name ?: '-'];
            } else {
                $seen[$sig] = $name;
            }
            if (! $name) {
                continue;
            }
            $skip = false;
            foreach ($ignored as $prefix) {
                if (str_starts_with($name, $prefix) || $name === rtrim($prefix, '.')) {
                    $skip = true;
                    break;
                }
            }
            if (
                ! $skip
                && ! $registry->isDomainAuthorizedRoute($name)
                && $registry->routeSurface($name) === null
            ) {
                $unmapped[$name] = $route->uri();
            }
        }
        if ($duplicates) {
            $this->warn('Potential duplicate route signatures:');
            $this->table(['Method + URI', 'Route A', 'Route B'], $duplicates);
        } else {
            $this->info('No duplicate method+URI signatures detected.');
        }
        $this->newLine();
        if ($unmapped === []) {
            $this->info('No unexplained unmapped named routes detected.');

            return self::SUCCESS;
        }

        $this->warn('Unmapped named routes are NOT automatically hidden by the surface system. Review before STRICT mode.');
        foreach (array_slice($unmapped, 0, 100, true) as $name => $uri) {
            $this->line(" - {$name}  [{$uri}]");
        }
        if (count($unmapped) > 100) {
            $this->line(' ... '.(count($unmapped) - 100).' more');
        }

        return self::SUCCESS;
    }
}
