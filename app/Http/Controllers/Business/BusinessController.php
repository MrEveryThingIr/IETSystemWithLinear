<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Business;
use App\Models\Profession;
use App\Services\Business\BusinessService;
use App\Support\BusinessAccess;
use App\Support\BusinessDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor && $actor->status === 'active', 403);

        $businesses = Business::query()
            ->where('owner_actor_id', $actor->getKey())
            ->orWhereHas('memberships', fn ($q) => $q
                ->where('actor_id', $actor->getKey())
                ->where('status', 'active'))
            ->withCount([
                'memberships as active_members_count' => fn ($q) => $q->where('status', 'active'),
                'contactPoints',
                'addresses',
            ])
            ->latest()
            ->get();

        return view('businesses.index', [
            'businesses' => $businesses,
            'kindLabels' => BusinessDirectory::KINDS,
        ]);
    }

    public function create(): View
    {
        return view('businesses.create', [
            'kindLabels' => BusinessDirectory::KINDS,
        ]);
    }

    public function store(
        Request $request,
        BusinessService $service
    ): RedirectResponse {
        $data = $request->validate($this->rules());

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor && $actor->status === 'active', 403);

        $business = $service->create($actor, $data);

        return redirect()
            ->route('businesses.show', $business)
            ->with('status', 'کسب‌وکار شما ساخته شد. حالا اطلاعات تماس، آدرس و اعضا را کامل کنید.');
    }

    public function show(Request $request, Business $business): View
    {
        abort_unless(BusinessAccess::canView($request->user(), $business), 403);

        $business->load([
            'owner.user',
            'contactPoints',
            'addresses',
            'memberships' => fn ($q) => $q
                ->with(['actor.user', 'professions.parent'])
                ->where('status', 'active')
                ->orderByRaw("case role when 'owner' then 1 when 'manager' then 2 else 3 end")
                ->orderBy('id'),
        ]);

        $professions = Profession::query()
            ->with('parent')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('businesses.show', [
            'business' => $business,
            'professions' => $professions,
            'canManage' => BusinessAccess::canManage($request->user(), $business),
            'canManageOwnership' => BusinessAccess::canManageOwnership($request->user(), $business),
            'kindLabels' => BusinessDirectory::KINDS,
            'roleLabels' => BusinessDirectory::ROLES,
            'visibilityLabels' => BusinessDirectory::VISIBILITIES,
        ]);
    }

    public function update(
        Request $request,
        Business $business
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $business->update($request->validate($this->rules()));

        return back()->with('status', 'اطلاعات کسب‌وکار به‌روزرسانی شد.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:220'],
            'kind' => ['required', Rule::in(array_keys(BusinessDirectory::KINDS))],
            'short_intro' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'founded_year' => ['nullable', 'integer', 'min:1200', 'max:2200'],
            'status' => ['nullable', Rule::in(['active', 'paused'])],
            'visibility' => ['required', Rule::in(array_keys(BusinessDirectory::VISIBILITIES))],
        ];
    }
}
