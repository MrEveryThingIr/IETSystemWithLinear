<?php

namespace App\Support;

use Closure;

/**
 * Explicit malware-scanner capability and policy (config/media.php).
 *
 * The scanner runs only when it is enabled, a binary is configured, and the
 * host can execute processes. Otherwise uploads are recorded as unscanned
 * (scan_status=unavailable), never as clean. Whether an unscanned Asset may be
 * used is decided by the "required" policy, never by APP_ENV.
 */
class MediaScannerPolicy
{
    /** @param  (Closure(): bool)|null  $processProbe  override for process availability detection (tests) */
    public function __construct(private readonly ?Closure $processProbe = null) {}

    public function enabled(): bool
    {
        return (bool) config('media.scanner.enabled', true);
    }

    public function required(): bool
    {
        return (bool) config('media.scanner.required', true);
    }

    public function binary(): string
    {
        return trim((string) config('media.scanner.binary', 'clamscan'));
    }

    public function timeout(): int
    {
        return max(1, (int) config('media.scanner.timeout', 120));
    }

    public function processExecutionAvailable(): bool
    {
        if ($this->processProbe instanceof Closure) {
            return (bool) ($this->processProbe)();
        }

        return ProcessExecution::available();
    }

    /** True only when a scan can actually be attempted on this host. */
    public function canScan(): bool
    {
        return $this->enabled() && $this->binary() !== '' && $this->processExecutionAvailable();
    }

    /** Whether an Asset without a malware scan may become ready and publishable. */
    public function allowsUnscannedAssets(): bool
    {
        return ! $this->required();
    }

    /** Human-readable reason a scan cannot run, or null when it can. */
    public function unavailableReason(): ?string
    {
        if (! $this->enabled()) {
            return 'Media scanning is disabled by configuration.';
        }

        if ($this->binary() === '') {
            return 'No media scanner binary is configured.';
        }

        if (! $this->processExecutionAvailable()) {
            return 'Process execution (proc_open) is unavailable on this host.';
        }

        return null;
    }
}
