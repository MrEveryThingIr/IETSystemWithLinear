<?php

namespace App\Http\Controllers;

use App\Support\PublicContent\GuestContentAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;

/** The site's front page: sign-in, plus the latest public Content when the public index is on. */
class HomeController extends Controller
{
    public function __invoke(GuestContentAccess $access): View
    {
        $cards = [];
        $limit = max(0, (int) config('public_content.home_preview', 6));

        if ((bool) config('public_content.index') && $limit > 0) {
            // The front page must always load, including during an upgrade
            // before migrations ran or while the database is unavailable.
            try {
                $latest = GuestContentController::publicQuery()
                    ->with(['activeRevision.assets', 'author.user', 'author.profile', 'blueprintVersion.blueprint'])
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get()
                    ->all();
                $cards = GuestContentController::cards($latest, $access);
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        return view('welcome', ['publicCards' => $cards]);
    }
}
