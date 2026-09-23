<?php

namespace App\Actions\Development;

use App\Models\Actor;
use App\Models\DevelopmentOrigin;
use App\Models\User;
use App\PlatformCapability;
use Carbon\CarbonImmutable;

class CaptureDevelopmentOrigin
{
    /**
     * @param  list<string>  $repositoryPaths
     */
    public function execute(
        User $user,
        string $sourceType,
        ?string $sourceUrl,
        string $title,
        string $summary,
        ?string $phaseKey,
        ?string $systemVersion,
        ?string $branch,
        ?string $baselineCommitSha,
        ?string $resultCommitSha,
        array $repositoryPaths,
        ?string $occurredAt = null,
        ?DevelopmentOrigin $supersedes = null,
    ): DevelopmentOrigin {
        abort_unless($user->hasPlatformCapability(PlatformCapability::ViewPlatformAudit), 403);

        $sourceType = strtolower(trim($sourceType));
        abort_unless(in_array($sourceType, DevelopmentOrigin::SOURCE_TYPES, true), 422, 'Choose a supported development-origin source.');

        $title = trim($title);
        $summary = trim($summary);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Development-origin title is required and may not exceed 255 characters.');
        abort_if($summary === '' || mb_strlen($summary) > 20_000, 422, 'Development-origin summary is required and may not exceed 20,000 characters.');

        $sourceUrl = $this->nullable($sourceUrl);
        if ($sourceUrl !== null) {
            abort_if(mb_strlen($sourceUrl) > 2000 || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false, 422, 'Development-origin source URL is invalid.');
            $scheme = strtolower((string) parse_url($sourceUrl, PHP_URL_SCHEME));
            abort_unless(in_array($scheme, ['http', 'https'], true), 422, 'Development-origin source URL must use HTTP or HTTPS.');
        }

        $phaseKey = $this->bounded($phaseKey, 80, 'Phase key');
        $systemVersion = $this->bounded($systemVersion, 80, 'System version');
        $branch = $this->bounded($branch, 160, 'Branch');

        $baselineCommitSha = $this->commitSha($baselineCommitSha);
        $resultCommitSha = $this->commitSha($resultCommitSha);

        $paths = collect($repositoryPaths)
            ->map(fn (mixed $path): string => trim((string) $path))
            ->filter()
            ->unique()
            ->values();

        abort_if($paths->count() > 30, 422, 'A development origin may reference at most 30 repository paths.');

        foreach ($paths as $path) {
            abort_if(mb_strlen($path) > 255 || str_contains($path, '..') || str_starts_with($path, '/'), 422, 'A repository path is invalid.');
        }

        $actor = $this->actor($user);

        return DevelopmentOrigin::query()->create([
            'created_by_actor_id' => $actor->id,
            'supersedes_origin_id' => $supersedes?->id,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'title' => $title,
            'summary' => $summary,
            'phase_key' => $phaseKey,
            'system_version' => $systemVersion,
            'branch' => $branch,
            'baseline_commit_sha' => $baselineCommitSha,
            'result_commit_sha' => $resultCommitSha,
            'repository_paths' => $paths->all(),
            'occurred_at' => $occurredAt !== null && trim($occurredAt) !== ''
                ? CarbonImmutable::parse($occurredAt)
                : null,
        ]);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    private function nullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function bounded(?string $value, int $max, string $label): ?string
    {
        $value = $this->nullable($value);
        abort_if($value !== null && mb_strlen($value) > $max, 422, "{$label} may not exceed {$max} characters.");

        return $value;
    }

    private function commitSha(?string $value): ?string
    {
        $value = $this->nullable($value);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);
        abort_unless(preg_match('/^[a-f0-9]{40}$/', $value) === 1, 422, 'Git commit references must be full 40-character SHA-1 values.');

        return $value;
    }
}
