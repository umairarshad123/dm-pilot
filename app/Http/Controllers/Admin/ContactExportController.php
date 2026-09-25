<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactFilterRequest;
use App\Services\Contacts\ContactService;
use App\Support\CurrentPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of contacts: everything matching the current filters (GET) or the selected rows (POST ids[]).
 * Both stay inside the page switcher's scope. Columns/format come from ContactService::exportCsv().
 */
class ContactExportController extends Controller
{
    public function __invoke(ContactFilterRequest $request, CurrentPage $currentPage): StreamedResponse
    {
        $ids = null;

        if ($request->isMethod('post')) {
            $request->validate([
                'ids' => ['required', 'array', 'max:5000'],
                'ids.*' => ['integer', 'min:1'],
            ], ['ids.required' => 'Select at least one contact to export.']);

            $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));
        }

        $filters = $request->filters($currentPage);
        $reachable = $ids === null && $request->reachable();
        $pageId = $currentPage->id();

        // ContactService builds the rows from query(); narrow it with the page scope, "reachable" and the selection.
        $exporter = new class($ids, $reachable, $pageId) extends ContactService
        {
            public function __construct(private ?array $ids, private bool $reachable, private ?int $pageId) {}

            public function query(array $filters = []): Builder
            {
                $query = parent::query($filters);

                if ($this->pageId !== null) {
                    $query->where('meta_account_id', $this->pageId);
                }

                if ($this->ids !== null) {
                    $query->whereKey($this->ids);
                }

                if ($this->reachable) {
                    $query->where(fn (Builder $q) => $q
                        ->where(fn (Builder $e) => $e->whereNotNull('email')->where('email', '!=', ''))
                        ->orWhere(fn (Builder $p) => $p->whereNotNull('phone')->where('phone', '!=', '')));
                }

                return $query;
            }
        };

        $name = 'contacts-'.($ids !== null ? 'selected-' : '').Carbon::now()->format('Y-m-d-His').'.csv';

        return $exporter->exportCsv($ids !== null ? ['page_id' => $filters['page_id'] ?? null] : $filters, $name);
    }
}
