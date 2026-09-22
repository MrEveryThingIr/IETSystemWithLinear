<?php

namespace App\Actions\Contexts;

use App\ContextKind;
use App\Models\Context;
use App\Models\GroupSpace;
use App\Models\GroupSpaceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureGroupSpaceContext
{
    public function execute(GroupSpace $space): Context
    {
        return DB::transaction(function () use ($space): Context {
            $locked = GroupSpace::query()->lockForUpdate()->findOrFail($space->id);

            $binding = GroupSpaceContext::query()
                ->with('context')
                ->where('group_space_id', $locked->id)
                ->first();

            if ($binding instanceof GroupSpaceContext) {
                return $binding->context;
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::GroupSpace,
            ]);

            GroupSpaceContext::query()->create([
                'context_id' => $context->id,
                'group_space_id' => $locked->id,
            ]);

            return $context->refresh();
        }, attempts: 3);
    }
}
