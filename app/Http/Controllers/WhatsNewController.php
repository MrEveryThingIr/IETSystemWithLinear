<?php

namespace App\Http\Controllers;

use App\Support\Changelog;
use Illuminate\Contracts\View\View;

class WhatsNewController extends Controller
{
    public function __invoke(): View
    {
        return view('whats-new', [
            'version' => Changelog::currentVersion(),
            'entries' => Changelog::entries(),
        ]);
    }
}
