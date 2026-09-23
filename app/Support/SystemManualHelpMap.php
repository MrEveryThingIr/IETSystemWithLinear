<?php

namespace App\Support;

class SystemManualHelpMap
{
    public function topicForRoute(?string $routeName): string
    {
        $routeName = (string) $routeName;

        if ($routeName === 'manual') {
            return 'feedback';
        }

        if (str_starts_with($routeName, 'contexts.contents.ai')
            || $routeName === 'platform.development-origins') {
            return 'ai-assistance';
        }

        if (str_starts_with($routeName, 'profile.')
            || str_starts_with($routeName, 'intents.')) {
            return 'profile-concepts';
        }

        if (str_starts_with($routeName, 'access-invitations.')
            || $routeName === 'getting-started') {
            return 'identity';
        }

        if (str_starts_with($routeName, 'actors.')
            || str_starts_with($routeName, 'platform.')) {
            return 'identity';
        }

        if (str_starts_with($routeName, 'invitations.')
            || str_starts_with($routeName, 'admissions.')) {
            return 'admission';
        }

        if (str_starts_with($routeName, 'contexts.submissions.')) {
            return 'submissions';
        }

        if (str_starts_with($routeName, 'content-evidence.')
            || str_contains($routeName, '.revisions.')) {
            return 'evidence';
        }

        if (str_starts_with($routeName, 'contexts.contents.studio')
            || str_contains($routeName, '.appearance')
            || str_contains($routeName, '.blocks')
            || str_contains($routeName, '.outline')) {
            return 'reader';
        }

        if (str_starts_with($routeName, 'contexts.contents.')
            || str_starts_with($routeName, 'groups.contents.')) {
            return 'content';
        }

        if (str_contains($routeName, 'agreement')) {
            return 'agreements';
        }

        if (str_starts_with($routeName, 'groups.')) {
            return 'groups';
        }

        if (str_starts_with($routeName, 'contexts.')) {
            return 'contexts';
        }

        if ($routeName === 'dashboard') {
            return 'mental-model';
        }

        return 'mental-model';
    }

    public function chapterTitle(string $topic): ?string
    {
        return SystemManualContent::CHAPTER_TITLES[$topic] ?? null;
    }
}
