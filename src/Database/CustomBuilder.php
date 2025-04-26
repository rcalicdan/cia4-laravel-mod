<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomBuilder extends Builder
{
    /**
     * Paginate the given query and append query string parameters.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginateWithQueryString($perPage = 15)
    {
        $paginator = $this->paginate($perPage);
        $request = \Config\Services::request();
        $paginator->setPath(base_url($request->getPath()));
        $queryParams = $request->getGet();
        unset($queryParams['page']);
        $paginator->appends($queryParams);

        return $paginator;
    }
}
