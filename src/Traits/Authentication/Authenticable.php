<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

trait Authenticable
{
    protected static function boot()
    {
        parent::boot();

        $clearCache = function ($user) {
            if (function_exists('session') && session()->get('auth_user_id') == $user->id) {
                session()->remove('auth_user_data');
                session()->remove('auth_user_cache');
            }
        };

        static::saved($clearCache);
        static::updated($clearCache);
        static::deleted($clearCache);
    }
}
