<?php

namespace App\Support\Bookkeeping;

use App\Enums\Bookkeeping\EntryOrigin;

/**
 * Provenance for an entry recorded by an IET workflow: what produced it and
 * which version of that record. One source version yields at most one live
 * entry across all books.
 */
final readonly class EntrySource
{
    public function __construct(
        public EntryOrigin $origin,
        public string $type,
        public string $uuid,
        public ?int $version = null,
    ) {}

    /** @return array{origin: string, source_type: string, source_uuid: string, source_version: int|null} */
    public function columns(): array
    {
        return [
            'origin' => $this->origin->value,
            'source_type' => $this->type,
            'source_uuid' => $this->uuid,
            'source_version' => $this->version,
        ];
    }

    public function idempotencyKey(): string
    {
        return 'iet:'.$this->type.':'.$this->uuid.':v'.($this->version ?? 0);
    }
}
