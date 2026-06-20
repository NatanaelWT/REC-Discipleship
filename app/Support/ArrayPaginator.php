<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ArrayPaginator
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function paginate(
        array $items,
        Request $request,
        int $defaultPerPage = 50,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $perPage = max(1, min(100, (int) $request->query('per_page', $defaultPerPage)));
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, min($lastPage, (int) $request->query($pageName, 1)));

        return new LengthAwarePaginator(
            array_slice(array_values($items), ($currentPage - 1) * $perPage, $perPage),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => $pageName,
            ],
        );
    }
}
