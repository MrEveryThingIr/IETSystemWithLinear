<?php

namespace App\Support;

use Carbon\CarbonInterface;

final readonly class HomeActionItem
{
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public ?string $summary,
        public string $url,
        public CarbonInterface $occurredAt,
    ) {}
}
