<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class WorkspaceController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }
}
