<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\ActorProfession;
use App\Models\Profession;
use App\Services\Contacts\ContactDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfessionProfileController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $actor = $this->actor($request);

        $selected = ActorProfession::query()
            ->with('profession.parent')
            ->where('actor_id', $actor->getKey())
            ->orderByDesc('is_primary')
            ->get();

        $professions = Profession::query()
            ->with('parent')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('profile.professions.index', compact(
            'user',
            'selected',
            'professions'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);

        $data = $request->validate([
            'profession_id' => ['required', 'exists:professions,id'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'visibility' => ['required', Rule::in(ContactDirectoryService::VISIBILITIES)],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($actor, $data): void {
            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                ActorProfession::query()
                    ->where('actor_id', $actor->getKey())
                    ->update(['is_primary' => false]);
            }

            $existing = ActorProfession::query()
                ->where('actor_id', $actor->getKey())
                ->where('profession_id', $data['profession_id'])
                ->first();

            if ($existing) {
                $existing->update([
                    'level' => $data['level'],
                    'years_experience' => $data['years_experience'] ?? null,
                    'visibility' => $data['visibility'],
                    'is_primary' => $isPrimary || $existing->is_primary,
                ]);

                return;
            }

            $profession = new ActorProfession([
                'profession_id' => $data['profession_id'],
                'level' => $data['level'],
                'years_experience' => $data['years_experience'] ?? null,
                'visibility' => $data['visibility'],
                'is_primary' => $isPrimary,
            ]);

            $profession->actor()->associate($actor);
            $profession->save();
        });

        return back()->with('status', 'تخصص شما ثبت شد.');
    }

    public function destroy(Request $request, ActorProfession $actorProfession): RedirectResponse
    {
        abort_unless(
            (int) $actorProfession->actor_id === (int) $this->actor($request)->getKey(),
            404
        );

        $wasPrimary = $actorProfession->is_primary;
        $actorProfession->delete();

        if ($wasPrimary) {
            ActorProfession::query()
                ->where('actor_id', $this->actor($request)->getKey())
                ->oldest()
                ->first()
                ?->update(['is_primary' => true]);
        }

        return back()->with('status', 'تخصص حذف شد.');
    }

    private function actor(Request $request): Actor
    {
        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor && $actor->status === 'active', 403);

        return $actor;
    }
}
