<?php

use Rcalicdan\Ci4Larabridge\Blade\BladeService;

// Initialize the extended blade service
$bladeService = new BladeService;

$bladeService->addComponentsPath(APPPATH . 'Views/admin/components');

// You can also register components manually
$bladeService->registerComponents([
    'special-button' => 'custom.views.special-button',
]);
