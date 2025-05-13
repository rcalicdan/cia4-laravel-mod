<?php

namespace Rcalicdan\Ci4Larabridge\Traits;

use Illuminate\Pagination\LengthAwarePaginator;

trait SearchPaginationTrait
{
    /**
     * Enhance a paginator instance with base path and query parameters.
     */
    protected function setupPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        $paginator->setPath(base_url($this->request->getPath()));
        $getParams = $this->request->getGet();
        unset($getParams['page']);
        $paginator->appends($getParams);

        return $paginator;
    }

    /**
     * Override paginate to include setup logic.
     *
     * @param  mixed  $query
     * @param  int  $perPage
     */
    protected function searchPaginateQuery($query, $perPage = 10): LengthAwarePaginator
    {
        $paginator = $query->paginate($perPage);

        return $this->setupPaginator($paginator);
    }
}
