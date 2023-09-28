<?php
/*
* File:     PaginatedCollection.php
* Category: Collection
* Author:   M. Goldenbaum
* Created:  16.03.18 03:13
* Updated:  -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Class PaginatedCollection.
 */
class PaginatedCollection extends Collection
{
    /**
     * Number of total entries.
     */
    protected int $total = 0;

    /**
     * Paginate the current collection.
     */
    public function paginate(int $per_page = 15, int $page = null, string $page_name = 'page', bool $prepaginated = false): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($page_name);

        $total = $this->total ?: $this->count();

        $results = ! $prepaginated && $total ? $this->forPage($page, $per_page)->toArray() : $this->all();

        return $this->paginator($results, $total, $per_page, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $page_name,
        ]);
    }

    /**
     * Create a new length-aware paginator instance.
     */
    protected function paginator(array $items, int $total, int $per_page, ?int $current_page, array $options): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total, $per_page, $current_page, $options);
    }

    /**
     * Get and set the total amount.
     *
     * @param  null  $total
     */
    public function total($total = null): ?int
    {
        if ($total === null) {
            return $this->total;
        }

        return $this->total = $total;
    }
}
