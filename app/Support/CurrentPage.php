<?php

namespace App\Support;

use App\Models\MetaAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin's currently selected Page / Instagram account ("page context").
 *
 * Stored in the session; null means "All pages". Resolved once per request
 * (registered as a scoped singleton in ViewServiceProvider) and shared with
 * every admin view as $currentPage.
 *
 *   $currentPage->id()            ?int     selected MetaAccount id (null = all pages)
 *   $currentPage->account()       ?MetaAccount
 *   $currentPage->isAll()         bool
 *   $currentPage->label()         string   "All pages" or the page name
 *   $currentPage->set(?int $id)   void     select a page (null = all)
 *   $currentPage->scope($query)   Builder  where meta_account_id = id() when a page is selected
 *   $currentPage->connectedPages() Collection<MetaAccount> (active first, then by name)
 */
class CurrentPage
{
    public const SESSION_KEY = 'current_meta_account_id';

    private bool $resolved = false;

    private ?MetaAccount $account = null;

    private ?Collection $pages = null;

    /** The request the memoized state belongs to (guards against reuse across requests, e.g. in tests). */
    private ?object $request = null;

    public function id(): ?int
    {
        return $this->account()?->id;
    }

    public function account(): ?MetaAccount
    {
        $this->refreshForRequest();

        if ($this->resolved) {
            return $this->account;
        }

        $this->resolved = true;
        $id = session(self::SESSION_KEY);

        if ($id === null) {
            return $this->account = null;
        }

        $this->account = MetaAccount::query()->find((int) $id);

        // The selected page was deleted: fall back to "All pages".
        if ($this->account === null) {
            session()->forget(self::SESSION_KEY);
        }

        return $this->account;
    }

    public function set(?int $id): void
    {
        if ($id === null) {
            session()->forget(self::SESSION_KEY);
        } else {
            session()->put(self::SESSION_KEY, $id);
        }

        $this->resolved = false;
        $this->account = null;
    }

    public function isAll(): bool
    {
        return $this->id() === null;
    }

    public function label(): string
    {
        return $this->account()?->page_name ?: ($this->isAll() ? 'All pages' : 'Page #'.$this->id());
    }

    /**
     * Constrain a query to the selected page (no-op for "All pages").
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function scope(Builder $query, string $column = 'meta_account_id'): Builder
    {
        $id = $this->id();

        return $id === null ? $query : $query->where($column, $id);
    }

    /** @return Collection<int, MetaAccount> */
    public function connectedPages(): Collection
    {
        $this->refreshForRequest();

        return $this->pages ??= MetaAccount::query()
            ->orderByDesc('active')
            ->orderBy('page_name')
            ->get(['id', 'page_name', 'platform', 'active', 'page_id', 'instagram_account_id']);
    }

    private function refreshForRequest(): void
    {
        $request = app()->bound('request') ? app('request') : null;

        if ($request !== $this->request) {
            $this->request = $request;
            $this->resolved = false;
            $this->account = null;
            $this->pages = null;
        }
    }
}
