<?php

namespace App\Http\Controllers;

use App\Models\Context;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use Illuminate\Http\RedirectResponse;

class LegacyGroupContentRedirectController extends Controller
{
    public function index(Group $group, GroupSpace $space): RedirectResponse
    {
        return redirect()->route('contexts.contents.index', $this->context($group, $space));
    }

    public function studio(Group $group, GroupSpace $space, SpaceContent $content): RedirectResponse
    {
        return redirect()->route('contexts.contents.studio', [$this->context($group, $space, $content), $content]);
    }

    public function blocks(Group $group, GroupSpace $space, SpaceContent $content): RedirectResponse
    {
        return redirect()->route('contexts.contents.blocks', [$this->context($group, $space, $content), $content]);
    }

    public function appearance(Group $group, GroupSpace $space, SpaceContent $content): RedirectResponse
    {
        return redirect()->route('contexts.contents.appearance', [$this->context($group, $space, $content), $content]);
    }

    public function outline(Group $group, GroupSpace $space, SpaceContent $content): RedirectResponse
    {
        return redirect()->route('contexts.contents.outline', [$this->context($group, $space, $content), $content]);
    }

    public function show(Group $group, GroupSpace $space, SpaceContent $content): RedirectResponse
    {
        return redirect()->route('contexts.contents.show', [$this->context($group, $space, $content), $content]);
    }

    private function context(Group $group, GroupSpace $space, ?SpaceContent $content = null): Context
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);

        if ($content instanceof SpaceContent) {
            abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        }

        $space->loadMissing('contextBinding.context');
        $context = $space->contextBinding?->context;
        abort_unless($context instanceof Context, 404);

        return $context;
    }
}
