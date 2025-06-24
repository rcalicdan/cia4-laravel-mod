<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class CustomBuilder extends Builder
{
    /**
     * Paginate the given query and append query string parameters.
     *
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function paginateWithQueryString($perPage = 15)
    {
        $paginator = $this->paginate($perPage);
        $this->appendQueryString($paginator);

        return $paginator;
    }

    /**
     * Simple paginate the given query and append query string parameters.
     *
     * @param  int  $perPage
     * @return Paginator
     */
    public function simplePaginateWithQueryString($perPage = 15)
    {
        $paginator = $this->simplePaginate($perPage);
        $this->appendQueryString($paginator);

        return $paginator;
    }

    /**
     * Cursor paginate the given query and append query string parameters.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $cursorName
     * @param  string|null  $cursor
     * @return CursorPaginator
     */
    public function cursorPaginateWithQueryString($perPage = 15, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        $paginator = $this->cursorPaginate($perPage, $columns, $cursorName, $cursor);
        $this->appendQueryString($paginator);

        return $paginator;
    }

    /**
     * Append query string parameters to the paginator.
     *
     * @param  LengthAwarePaginator|Paginator|CursorPaginator  $paginator
     * @return void
     */
    protected function appendQueryString($paginator)
    {
        $request = \Config\Services::request();
        $paginator->setPath(base_url($request->getPath()));

        $queryParams = $request->getGet();

        unset($queryParams['page']);
        unset($queryParams['cursor']);

        $paginator->appends($queryParams);
    }
}
