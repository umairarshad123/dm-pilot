<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CurrentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PageSwitchController extends Controller
{
    /** Select the Page / IG account the admin is working in (empty = all pages). */
    public function __invoke(Request $request, CurrentPage $currentPage): RedirectResponse
    {
        $data = $request->validate([
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
        ]);

        $currentPage->set(isset($data['meta_account_id']) ? (int) $data['meta_account_id'] : null);

        return back(fallback: route('admin.dashboard'))
            ->with('success', 'Now viewing '.$currentPage->label().'.');
    }
}
