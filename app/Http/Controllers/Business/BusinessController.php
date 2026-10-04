<?php

namespace App\Http\Controllers\Business;

use App\Actions\Contexts\EnsureBusinessContext;
use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Business;
use App\Models\Profession;
use App\Services\Business\BusinessService;
use App\Services\Business\EnsureRealEstateBusinessIntake;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\BusinessAccess;
use App\Support\BusinessDirectory;
use App\Support\BusinessEconomyProjection;
use App\Support\ExternalMoneyGatewayRegistry;
use App\Support\PlatformAdmin;
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

        $businessQuery = Business::query();

        if (! PlatformAdmin::check($user)) {
            $businessQuery->where(fn ($query) => $query
                ->where('owner_actor_id', $actor->getKey())
                ->orWhereHas('memberships', fn ($membership) => $membership
                    ->where('actor_id', $actor->getKey())
                    ->where('status', 'active')));
        }

        $businesses = $businessQuery
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->query('kind')))
            ->withCount([
                'memberships as active_members_count' => fn ($q) => $q->where('status', 'active'),
                'businessContacts',
                'listings',
                'publicIntakePortals',
            ])
            ->latest()
            ->get();

        return view('businesses.index', [
            'businesses' => $businesses,
            'kindLabels' => BusinessDirectory::kindLabels(),
        ]);
    }

    public function create(): View
    {
        return view('businesses.create', [
            'kindLabels' => BusinessDirectory::kindLabels(),
            'visibilityLabels' => BusinessDirectory::visibilityLabels(),
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
            ->with('status', __('business.messages.created'));
    }

    public function show(
        Request $request,
        Business $business,
        EnsureBusinessContext $contexts,
        ExternalMoneyGatewayRegistry $externalGateways,
        FeatureSurfaceAccess $surfaceAccess,
        BusinessEconomyProjection $economy,
    ): View {
        $canOperate = BusinessAccess::canOperate($request->user(), $business);
        abort_unless($canOperate, 403);

        $business->load(['owner.user']);

        $context = $contexts->execute($business);

        $business->load([
            'contactPoints',
            'addresses',
            'defaultMonetaryUnit',
            'businessContacts.contactPoints',
            'categories',
            'listings.currentVersion',
            'publicIntakePortals',
            'contextBinding.context',
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

        $routineCount = $context->plans()->count();
        $economyProjection = $economy->forBusiness($business);

        $canManage = BusinessAccess::canManage($request->user(), $business);
        $canUsePlanner = $surfaceAccess->allows($request->user(), 'planner');
        $canUseDeals = $surfaceAccess->allows($request->user(), 'deals');
        $canUseMarket = $surfaceAccess->allows($request->user(), 'market');
        $canUseMoney = collect(['money', 'accounting', 'exchange'])
            ->contains(fn (string $surface): bool => $surfaceAccess->allows($request->user(), $surface));

        $publicSiteUrl = $business->status === 'active' && $business->visibility === 'public'
            ? route('public.businesses.show', ['business' => $business->slug])
            : null;

        $featuredBusinessIds = collect(data_get($business->settings, 'public_site.featured_business_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $publicSiteCandidates = $canManage
            ? Business::query()
                ->where('id', '!=', $business->getKey())
                ->where('status', 'active')
                ->where('visibility', 'public')
                ->orderBy('name')
                ->get(['id', 'uuid', 'slug', 'name', 'short_intro', 'kind'])
            : collect();

        $businessSectionLinks = array_filter([
            'overview' => route('businesses.show', $business),
            'clients' => route('businesses.clients.index', $business),
            'catalog' => route('businesses.catalog.index', $business),
            'work' => $canUsePlanner ? route('planner.index', ['context' => $context->uuid]) : null,
            'deals' => $canUseDeals ? route('deals.index') : null,
            'money' => $canUseMoney ? route('money.index') : null,
            'manage' => $canManage ? route('businesses.show', $business).'#team-settings' : null,
        ]);

        $businessSections = collect(__('workflow.business.sections'))
            ->only(array_keys($businessSectionLinks))
            ->all();

        return view('businesses.show', [
            'business' => $business,
            'businessContext' => $context,
            'routineCount' => $routineCount,
            'economyProjection' => $economyProjection,
            'professions' => $professions,
            'externalMoneyGateways' => $externalGateways->available(),
            'canOperate' => $canOperate,
            'canManage' => $canManage,
            'canManageOwnership' => BusinessAccess::canManageOwnership($request->user(), $business),
            'canUsePlanner' => $canUsePlanner,
            'canUseDeals' => $canUseDeals,
            'canUseMarket' => $canUseMarket,
            'canUseMoney' => $canUseMoney,
            'publicSiteUrl' => $publicSiteUrl,
            'featuredBusinessIds' => $featuredBusinessIds,
            'publicSiteCandidates' => $publicSiteCandidates,
            'businessSections' => $businessSections,
            'businessSectionLinks' => $businessSectionLinks,
            'kindLabels' => BusinessDirectory::kindLabels(),
            'roleLabels' => BusinessDirectory::roleLabels(),
            'visibilityLabels' => BusinessDirectory::visibilityLabels(),
        ]);
    }

    public function update(
        Request $request,
        Business $business,
        EnsureRealEstateBusinessIntake $realEstateIntake,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $business->update($request->validate($this->rules()));

        if ($business->kind === 'real_estate') {
            $realEstateIntake->execute($business->refresh());
        }

        return back()->with('status', __('business.messages.updated'));
    }

    public function updatePublicSite(Request $request, Business $business): RedirectResponse
    {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'featured_business_ids' => ['nullable', 'array', 'max:12'],
            'featured_business_ids.*' => ['integer', 'distinct', Rule::exists('businesses', 'id')],
        ]);

        $requestedIds = collect($data['featured_business_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $allowedIds = Business::query()
            ->whereIn('id', $requestedIds)
            ->where('id', '!=', $business->getKey())
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $allowed = array_flip($allowedIds);
        $featuredIds = $requestedIds
            ->filter(fn (int $id): bool => isset($allowed[$id]))
            ->take(12)
            ->values()
            ->all();

        $settings = is_array($business->settings) ? $business->settings : [];
        $settings['public_site'] = array_replace(
            is_array($settings['public_site'] ?? null) ? $settings['public_site'] : [],
            ['featured_business_ids' => $featuredIds],
        );

        $business->update(['settings' => $settings]);

        return back()->with('status', __('business.public_site.saved'));
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
