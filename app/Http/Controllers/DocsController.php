<?php

namespace App\Http\Controllers;

use App\Models\DocContribution;
use App\Support\Docs\DocsAccess;
use App\Support\Docs\DocsCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** The public documentation and the form through which experts send proposals. It is not Content and shares nothing with it. */
class DocsController extends Controller
{
    public function __construct(private readonly DocsCatalog $catalog, private readonly DocsAccess $access) {}

    public function index(Request $request): View|Response
    {
        if (! $this->access->allows($request)) {
            return $this->restricted();
        }

        return view('docs.index', [
            'pages' => $this->catalog->pages(),
            'inviteOnly' => $this->access->inviteOnly(),
        ]);
    }

    public function show(Request $request, string $slug): View|Response
    {
        if (! $this->access->allows($request)) {
            return $this->restricted();
        }

        $pages = $this->catalog->pages();
        $page = $this->catalog->page($slug);
        abort_if($page === null, 404);

        $at = array_search($slug, array_column($pages, 'slug'), true);

        return view('docs.show', [
            'page' => $page,
            'previous' => $at !== false && $at > 0 ? $pages[$at - 1] : null,
            'next' => $at !== false ? ($pages[$at + 1] ?? null) : null,
            'inviteOnly' => $this->access->inviteOnly(),
        ]);
    }

    public function join(Request $request, string $token): RedirectResponse|Response
    {
        if (! $this->access->redeem($request, $token)) {
            return $this->restricted();
        }

        return redirect()->route('docs.index');
    }

    public function contribute(Request $request): View|Response
    {
        if (! $this->access->allows($request)) {
            return $this->restricted();
        }

        return view('docs.contribute', [
            'pages' => $this->catalog->pages(),
            'topic' => (string) $request->query('topic', 'general'),
            'field' => (string) $request->query('field', ''),
            'inviteOnly' => $this->access->inviteOnly(),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        abort_unless($this->access->allows($request), 403);

        // A hidden field only automated senders fill in: answer as if it worked and store nothing.
        if ($request->filled('website')) {
            return redirect()->route('docs.contribute')->with('docs.thanks', true);
        }

        $fields = (array) config('docs.fields');
        $data = $request->validate([
            'topic' => ['required', 'string', Rule::in(['general', ...$this->catalog->slugs()])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'field' => ['required', 'string', Rule::in($fields)],
            'field_other' => ['nullable', 'string', 'max:80', Rule::requiredIf($request->input('field') === 'other')],
            'expertise' => ['required', 'string', 'min:10', 'max:2000'],
            'education' => ['required', 'string', 'min:3', 'max:2000'],
            'experience' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'string', 'max:1000'],
            'proposal' => ['required', 'string', 'min:30', 'max:8000'],
            'agree' => ['accepted'],
            'may_credit' => ['nullable', 'boolean'],
        ]);

        DocContribution::query()->create([
            'user_id' => $request->user()?->id,
            'locale' => app()->getLocale(),
            'topic' => $data['topic'],
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'field' => $data['field'] === 'other' ? 'other: '.$data['field_other'] : $data['field'],
            'expertise' => $data['expertise'],
            'education' => $data['education'],
            'experience' => $data['experience'] ?? null,
            'links' => $data['links'] ?? null,
            'proposal' => $data['proposal'],
            'may_credit' => (bool) ($data['may_credit'] ?? false),
        ]);

        return redirect()->route('docs.contribute')->with('docs.thanks', true);
    }

    private function restricted(): Response
    {
        return response()->view('docs.restricted', [], 403);
    }
}
