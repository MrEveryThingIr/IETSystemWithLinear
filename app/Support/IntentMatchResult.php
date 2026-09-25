<?php

namespace App\Support;

use App\Models\ActorProfileIntent;

final readonly class IntentMatchResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public ActorProfileIntent $intent,
        public array $reasons,
        public int $alignedDimensions,
    ) {}
}
