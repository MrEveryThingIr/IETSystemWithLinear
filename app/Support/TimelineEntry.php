<?php

namespace App\Support;

use App\Models\Actor;
use Carbon\CarbonInterface;

final readonly class TimelineEntry
{
    public function __construct(
        public string $key,
        public string $kind,
        public string $title,
        public ?string $summary,
        public CarbonInterface $occurredAt,
        public ?Actor $actor,
        public string $url,
    ) {}
}
