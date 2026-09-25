<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Public legal pages required by Meta (App settings → Basic) and an app description page for reviewers. */
class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function dataDeletion(): View
    {
        return view('public.data-deletion');
    }

    public function about(): View
    {
        return view('public.about');
    }
}
