<?php

namespace Rcalicdan\Ci4Larabridge\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Rcalicdan\Ci4Larabridge\Database\CustomBuilder;

/**
 * Base Eloquent model for the Ci4Larabridge module.
 *
 * Extends Laravel's Eloquent Model to integrate a custom query builder, enabling
 * tailored query functionality within a CodeIgniter 4 application.
 */
class Model extends EloquentModel
{
    /**
     * Creates a new Eloquent query builder instance for the model.
     *
     * Overrides the default Eloquent query builder with a custom implementation
     * to support specialized query operations.
     *
     * @param \Illuminate\Database\Query\Builder $query The underlying query builder instance.
     * @return CustomBuilder The custom query builder instance.
     */
    public function newEloquentBuilder($query)
    {
        return new CustomBuilder($query);
    }
}
