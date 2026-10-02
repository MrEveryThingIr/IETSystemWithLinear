<?php

namespace App\Actions\Contexts;

use App\ContextKind;
use App\Models\Business;
use App\Models\BusinessContext;
use App\Models\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureBusinessContext
{
    public function execute(Business $business): Context
    {
        return DB::transaction(function () use ($business): Context {
            $lockedBusiness = Business::query()->lockForUpdate()->findOrFail($business->id);

            $binding = BusinessContext::query()
                ->with('context')
                ->where('business_id', $lockedBusiness->id)
                ->first();

            if ($binding instanceof BusinessContext) {
                return Context::query()->findOrFail($binding->context_id);
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::Business,
            ]);

            BusinessContext::query()->create([
                'context_id' => $context->id,
                'business_id' => $lockedBusiness->id,
            ]);

            return $context->refresh();
        }, attempts: 3);
    }
}
