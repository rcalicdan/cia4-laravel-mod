<?php

namespace Rcalicdan\Ci4Larabridge\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Rcalicdan\Ci4Larabridge\Database\CustomBuilder;

class Model extends EloquentModel
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Rcalicdan\Ci4Larabridge\Database\CustomBuilder|static
     */
    public function newEloquentBuilder($query)
    {
        return new CustomBuilder($query);
    }
}
