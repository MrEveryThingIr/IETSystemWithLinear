<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PublicIntakePortal;
use App\Support\PublicIntakeAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RealEstateWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $portals = PublicIntakePortal::query()
            ->with('business')
            ->where('type', 'real_estate')
            ->orderBy('title')
            ->get()
            ->filter(fn (PublicIntakePortal $portal): bool => PublicIntakeAccess::canView($user, $portal))
            ->values();

        return view('workspace.real-estate', compact('portals'));
    }
}
