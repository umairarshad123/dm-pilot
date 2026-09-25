{{--
    LEGACY bridge. Old pages use @extends('layouts.admin') + @section('title') + @section('content').
    They now render inside the new app shell with the scoped `.legacy` styles (resources/css/app.css).
    New pages should use <x-layouts.app> directly (see docs/UI_GUIDE.md); delete this file once
    nothing extends it any more.
--}}
<x-layouts.app :title="trim($__env->yieldContent('title', 'Admin'))" legacy>
    @yield('content')
</x-layouts.app>
