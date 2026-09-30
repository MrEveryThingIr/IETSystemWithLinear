<?php

namespace App\Http\Controllers\PublicIntake;

use App\Http\Controllers\Controller;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use App\Support\PublicIntakeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicRealEstateAdminController extends Controller
{
    public function index(Request $request, PublicIntakePortal $portal): View
    {
        abort_unless(PublicIntakeAccess::canView($request->user(), $portal), 403);

        $query = $portal->realEstateCases()->latest();

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));

            $query->where(function ($q) use ($term): void {
                $q->where('reference_code', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('public_area', 'like', "%{$term}%")
                    ->orWhere('exact_address', 'like', "%{$term}%")
                    ->orWhere('property_subtype', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%");
            });
        }

        foreach (['intent', 'transaction_mode', 'property_class', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $cases = $query->paginate(20)->withQueryString();

        return view('public-intake.real-estate.admin.index', [
            'portal' => $portal,
            'cases' => $cases,
            'stats' => [
                'total' => $portal->realEstateCases()->count(),
                'new' => $portal->realEstateCases()->where('status', 'new')->count(),
                'offers' => $portal->realEstateCases()->where('intent', 'offer')->count(),
                'needs' => $portal->realEstateCases()->where('intent', 'need')->count(),
            ],
        ]);
    }

    public function show(
        Request $request,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case
    ): View {
        abort_unless(PublicIntakeAccess::canView($request->user(), $portal), 403);
        abort_unless($case->public_intake_portal_id === $portal->getKey(), 404);

        return view('public-intake.real-estate.admin.show', [
            'portal' => $portal,
            'case' => $case,
            'canManage' => PublicIntakeAccess::canManage($request->user(), $portal),
        ]);
    }

    public function updateStatus(
        Request $request,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case
    ): RedirectResponse {
        abort_unless(PublicIntakeAccess::canManage($request->user(), $portal), 403);
        abort_unless($case->public_intake_portal_id === $portal->getKey(), 404);

        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,qualified,in_progress,closed,rejected'],
        ]);

        $case->update($data);

        return back()->with('status', 'وضعیت پرونده ذخیره شد.');
    }
}
