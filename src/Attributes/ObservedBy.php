<?php

namespace Rcalicdan\Ci4Larabridge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ObservedBy
{
    public array $observers;

    public function __construct(array $observers)
    {
        $this->observers = $observers;
    }
}