<?php

namespace App\Support;

final readonly class HomeGuidanceItem
{
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public string $summary,
        public string $consequence,
        public string $url,
        public string $cta,
    ) {}
}
