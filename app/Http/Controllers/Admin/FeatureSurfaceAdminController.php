<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureSurfaceGrant;
use App\Models\FeatureSurfaceSetting;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use App\Services\Surfaces\FeatureSurfaceRegistry;
use App\Support\PlatformAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeatureSurfaceAdminController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless(PlatformAdmin::check($request->user()), 403);
    }

    public function index(Request $request): View
    {
        $this->guard($request);
        $users = User::query()->withCount('featureSurfaceGrants')->orderBy('username')->orderBy('email')->paginate(30);

        return view('admin.surfaces.index', ['users' => $users, 'strict' => true]);
    }

    public function edit(Request $request, User $user, FeatureSurfaceRegistry $registry): View
    {
        $this->guard($request);

        return view('admin.surfaces.edit', [
            'subject' => $user,
            'groups' => $registry->groupedAvailable(grantableOnly: true),
            'granted' => FeatureSurfaceGrant::query()->where('user_id', $user->getKey())->pluck('surface_key')->all(),
        ]);
    }

    public function update(Request $request, User $user, FeatureSurfaceGrantService $service, FeatureSurfaceRegistry $registry): RedirectResponse
    {
        $this->guard($request);
        $validated = $request->validate([
            'surfaces' => ['sometimes', 'array'],
            'surfaces.*' => ['string', Rule::in($registry->grantableKeys())],
        ]);
        $resolved = $service->sync($user, $validated['surfaces'] ?? [], $request->user());

        return back()->with('status', __('publication.saved', ['count' => count($resolved)]));
    }

    public function mode(Request $request): RedirectResponse
    {
        $this->guard($request);
        $mode = $request->boolean('strict') ? 'strict' : 'observe';
        FeatureSurfaceSetting::setValue('enforcement_mode', $mode);

        return back()->with('status', $mode === 'strict'
            ? __('publication.mode_strict')
            : __('publication.mode_observe'));
    }
}
