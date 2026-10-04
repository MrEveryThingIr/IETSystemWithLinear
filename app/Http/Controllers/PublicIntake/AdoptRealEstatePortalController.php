<?php

namespace App\Http\Controllers\PublicIntake;

use App\Http\Controllers\Controller;
use App\Models\PublicIntakePortal;
use App\Services\Business\AdoptRealEstatePortalIntoBusiness;
use App\Support\PublicIntakeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdoptRealEstatePortalController extends Controller
{
    public function __invoke(
        Request $request,
        PublicIntakePortal $portal,
        AdoptRealEstatePortalIntoBusiness $adopt,
    ): RedirectResponse {
        abort_unless(PublicIntakeAccess::canManage($request->user(), $portal), 403);

        $business = $adopt->execute($portal, $request->user());

        return redirect()
            ->route('businesses.show', $business)
            ->with('status', __('business.messages.real_estate_adopted'));
    }
}
