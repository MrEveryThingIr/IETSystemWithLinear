<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Profession;
use App\Services\Business\BusinessService;
use App\Support\BusinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessTeamController extends Controller
{
    public function store(
        Request $request,
        Business $business,
        BusinessService $service
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'user' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['manager', 'member'])],
            'job_title' => ['nullable', 'string', 'max:160'],
        ]);

        $actor = $service->findActor($data['user']);

        $service->addMember(
            $business,
            $actor,
            $data['role'],
            $data['job_title'] ?? null
        );

        return back()->with('status', __('business.messages.member_added'));
    }

    public function update(
        Request $request,
        Business $business,
        BusinessMembership $membership,
        BusinessService $service
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $service->updateMember($business, $membership, $request->validate([
            'role' => ['required', Rule::in(['manager', 'member'])],
            'job_title' => ['nullable', 'string', 'max:160'],
        ]));

        return back()->with('status', __('business.messages.member_updated'));
    }

    public function destroy(
        Request $request,
        Business $business,
        BusinessMembership $membership,
        BusinessService $service
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $service->removeMember($business, $membership);

        return back()->with('status', __('business.messages.member_removed'));
    }

    public function assignProfession(
        Request $request,
        Business $business,
        BusinessMembership $membership,
        BusinessService $service
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'profession_id' => ['required', 'exists:professions,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $profession = Profession::query()->findOrFail($data['profession_id']);

        $service->assignProfession(
            $business,
            $membership,
            $profession,
            (bool) ($data['is_primary'] ?? false)
        );

        return back()->with('status', __('business.messages.profession_added'));
    }

    public function removeProfession(
        Request $request,
        Business $business,
        BusinessMembership $membership,
        Profession $profession
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $membership->business_id === (int) $business->getKey(), 404);

        $membership->professions()->detach($profession->getKey());

        return back()->with('status', __('business.messages.profession_removed'));
    }

    public function transferOwnership(
        Request $request,
        Business $business,
        BusinessService $service
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManageOwnership($request->user(), $business), 403);

        $data = $request->validate([
            'actor_id' => ['required', 'integer', 'exists:actors,id'],
        ]);

        $currentOwner = $request->user()?->actor;
        abort_unless($currentOwner instanceof Actor && $currentOwner->status === 'active', 403);

        $newOwner = Actor::query()->findOrFail($data['actor_id']);

        $service->transferOwnership(
            $business,
            $currentOwner,
            $newOwner
        );

        return back()->with('status', __('business.messages.ownership_transferred'));
    }
}
