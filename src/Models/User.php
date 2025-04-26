<?php

namespace Rcalicdan\Ci4Larabridge\Models;

use Rcalicdan\Ci4Larabridge\Models\Model;

class User extends Model
{
    /**
     * The table associated with the model
     *
     * @var string
     */
    protected $table = 'users';

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
