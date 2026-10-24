<?php

namespace App\Livewire\Intents;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Support\Categories\CategoryCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Directory extends Component
{
    use WithPagination;

    public string $quick = 'all';

    public string $kind = 'all';

    public string $subject = 'all';

    public string $arrangement = 'all';

    public string $exchange = 'all';

    public string $search = '';

    public string $location = '';

    public ?string $highlight = null;

    public int $area = 0;

    public string $scope = 'all';

    protected array $queryString = [
        'quick' => ['except' => 'all'],
        'kind' => ['except' => 'all'],
        'subject' => ['except' => 'all'],
        'arrangement' => ['except' => 'all'],
        'exchange' => ['except' => 'all'],
        'search' => ['except' => ''],
        'location' => ['except' => ''],
        'highlight' => ['except' => null],
        'area' => ['except' => 0],
        'scope' => ['except' => 'all'],
    ];

    public function updated(string $property): void
    {
        if (in_array($property, ['quick', 'kind', 'subject', 'arrangement', 'exchange', 'search', 'location', 'area', 'scope'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['quick', 'kind', 'subject', 'arrangement', 'exchange', 'search', 'location', 'area', 'scope']);
        $this->quick = 'all';
        $this->kind = 'all';
        $this->subject = 'all';
        $this->arrangement = 'all';
        $this->exchange = 'all';
        $this->area = 0;
        $this->scope = 'all';
        $this->resetPage();
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $query = ActorProfileIntent::query()
            ->where('status', ProfileIntentStatus::Active->value)
            ->with(['publishedVersion', 'concept.labels', 'profile.actor.user', 'profile.displayImage.asset', 'publisherBusiness', 'publisherGroup', 'media' => fn ($media) => $media->with('asset')]);

        $this->applyQuickFilter($query);

        if (in_array($this->kind, ['need', 'offer'], true)) {
            $query->where('kind', $this->kind);
        }

        if ($this->subject !== 'all') {
            $query->where('subject_kind', $this->subject);
        }

        if ($this->arrangement !== 'all') {
            $query->where('arrangement_kind', $this->arrangement);
        }

        if ($this->exchange !== 'all') {
            $query->where('exchange_preference', $this->exchange);
        }

        $location = trim($this->location);
        if ($location !== '') {
            $query->where('location_text', 'like', '%'.$location.'%');
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $scope) use ($search): void {
                $scope->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location_text', 'like', '%'.$search.'%')
                    ->orWhereHas('concept.labels', fn (Builder $labels) => $labels->where('label', 'like', '%'.$search.'%'));
            });
        }

        $query->visibleTo($user);

        if ($this->area > 0) {
            $query->whereIn('concept_id', $this->areaConceptIds($this->area));
        }

        if ($this->scope === 'mine') {
            $actorId = (int) $user->actor?->id;
            $memberships = ActorProfileIntent::membershipsOf($actorId);
            $query->where(fn (Builder $mine) => $mine->where('publisher_actor_id', $actorId)
                ->orWhereIn('publisher_business_id', $memberships['managed_businesses'])
                ->orWhereIn('publisher_group_id', $memberships['managed_groups']));
        }

        $intents = $query
            ->latest('updated_at')
            ->latest('id')
            ->paginate(24);

        $catalog = app(CategoryCatalog::class);
        $paths = [];
        foreach ($intents->getCollection() as $intent) {
            $paths[$intent->id] = implode(' › ', array_column($catalog->path((int) $intent->concept_id, null), 'label'));
        }

        $versionIds = $intents->getCollection()->pluck('business_listing_version_id')->filter()->unique()->values()->all();
        $listingCovers = $versionIds === [] ? [] : array_flip(DB::table('business_listing_media')
            ->whereIn('business_listing_version_id', $versionIds)->where('visibility', 'public')
            ->distinct()->pluck('business_listing_version_id')->map(fn ($id): int => (int) $id)->all());

        return view('livewire.intents.directory', [
            'intents' => $intents,
            'listingCovers' => $listingCovers,
            'areas' => $catalog->areas(),
            'paths' => $paths,
        ])->title(__('intents.directory.title'));
    }

    /** @param Builder<ActorProfileIntent> $query */
    private function applyQuickFilter(Builder $query): void
    {
        match ($this->quick) {
            'needs' => $query->where('kind', 'need'),
            'offers' => $query->where('kind', 'offer'),
            'services' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'service')->orWhere('arrangement_kind', 'service');
            }),
            'property' => $query->where('subject_kind', 'property'),
            'capital' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'capital')->orWhere('arrangement_kind', 'financing');
            }),
            'collaboration' => $query->where(function (Builder $scope): void {
                $scope->where('subject_kind', 'collaboration')->orWhere('arrangement_kind', 'collaboration');
            }),
            default => null,
        };
    }

    /**
     * The area itself, every shared category under it, and private categories
     * hanging below those (a few levels at most).
     *
     * @return list<int>
     */
    private function areaConceptIds(int $area): array
    {
        $catalog = app(CategoryCatalog::class);
        $ids = DB::table('concept_closure')
            ->where('scheme_id', $catalog->sharedSchemeId())
            ->where('ancestor_concept_id', $area)
            ->pluck('descendant_concept_id')
            ->map(fn ($id): int => (int) $id)
            ->push($area)
            ->unique()
            ->values()
            ->all();
        $narrower = (int) DB::table('concept_relation_types')->where('key', 'narrower_than')->value('id');
        $frontier = $ids;
        for ($depth = 0; $depth < CategoryCatalog::MAX_PRIVATE_DEPTH && $frontier !== [] && $narrower > 0; $depth++) {
            $frontier = DB::table('concept_relations')->where('relation_type_id', $narrower)->whereIn('to_concept_id', $frontier)
                ->pluck('from_concept_id')->map(fn ($id): int => (int) $id)->diff($ids)->values()->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }
}
