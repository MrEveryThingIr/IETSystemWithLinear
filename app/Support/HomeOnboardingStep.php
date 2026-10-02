<?php

namespace App\Support;

final readonly class HomeOnboardingStep
{
    public function __construct(
        public string $key,
        public string $title,
        public string $summary,
        public string $url,
        public string $cta,
        public bool $complete,
    ) {}
}
