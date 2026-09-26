<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Support\CurrentPage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Throwable;

class ViewServiceProvider extends ServiceProvider
{
    /** Product name shown in the sidebar, login page and <title>. Change it here only. */
    public const BRAND = 'Apex Chat Bot';

    public function register(): void
    {
        // One instance per request (Octane-safe), resolved lazily.
        $this->app->scoped(CurrentPage::class);
    }

    public function boot(): void
    {
        View::share('brand', self::BRAND);

        // App shell + every admin page: page context for filters and the page switcher.
        View::composer(['components.layouts.app', 'admin.*'], function (ViewInstance $view): void {
            if (! auth()->check()) {
                return;
            }

            $currentPage = app(CurrentPage::class);

            $view->with([
                'currentPage' => $currentPage,
                'connectedPages' => $currentPage->connectedPages(),
            ]);
        });

        // Sidebar counters (only the shell needs them).
        View::composer('components.layouts.app', function (ViewInstance $view): void {
            if (! auth()->check()) {
                return;
            }

            $view->with('navCounts', (function (): array {
                try {
                    $needsHuman = app(CurrentPage::class)
                        ->scope(Conversation::query())
                        ->where('status', 'open')
                        ->where('human_takeover', true)
                        ->count();
                } catch (Throwable) {
                    $needsHuman = 0;
                }

                return ['live_chat' => $needsHuman];
            })());
        });
    }
}
