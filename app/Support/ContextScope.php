<?php

namespace App\Support;

use App\ContextKind;
use App\Models\Context;
use LogicException;

final class ContextScope
{
    public static function assertLegacyGroupSpace(Context $context, ?int $groupSpaceId, string $subject): void
    {
        $context->loadMissing('groupSpaceBinding');

        if ($context->kind === ContextKind::GroupSpace) {
            $boundId = $context->groupSpaceBinding?->group_space_id;

            if ($boundId === null || $groupSpaceId === null || (int) $boundId !== $groupSpaceId) {
                throw new LogicException($subject.' GroupSpace compatibility does not match its Context.');
            }

            return;
        }

        if ($groupSpaceId !== null) {
            throw new LogicException($subject.' in a non-Group Context cannot carry GroupSpace provenance.');
        }
    }

    public static function legacyGroupSpaceId(Context $context): ?int
    {
        $context->loadMissing('groupSpaceBinding');

        return $context->kind === ContextKind::GroupSpace
            ? $context->groupSpaceBinding?->group_space_id
            : null;
    }

    public static function storageSegment(Context $context): string
    {
        return 'contexts/'.$context->uuid;
    }
}
