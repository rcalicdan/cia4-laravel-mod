<?php

namespace App\Events;

use Config\Services;

class PreSystem
{
    public static function registerEloquentCollector()
    {

        $toolbarConfig = config('Toolbar');
        $toolbarConfig->collectors[] = \Rcalicdan\Ci4Larabridge\Debug\Collectors\EloquentCollector::class;
        Services::toolbar($toolbarConfig, true); 
    }
}
