<?php

namespace Reymart221111\Models;

use Reymart221111\Libraries\Eloquent\BaseModel;
use Reymart221111\Traits\HashedPasswordTrait;
use Illuminate\Database\Eloquent\Model;

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
