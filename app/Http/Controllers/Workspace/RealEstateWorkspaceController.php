<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\PublicIntakePortal;
use App\Support\PlatformAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RealEstateWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $portals = PublicIntakePortal::query()
            ->when(! PlatformAdmin::check($user), fn ($q) => $q->whereHas('grants', fn ($g) => $g->where('user_id', $user->getKey())))
            ->where('type', 'real_estate')->orderBy('title')->get();

        return view('workspace.real-estate', compact('portals'));
    }
}
