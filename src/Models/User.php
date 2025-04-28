<?php

namespace Rcalicdan\Ci4Larabridge\Models;

class User extends Model
{
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
