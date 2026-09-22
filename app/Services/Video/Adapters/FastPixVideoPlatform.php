<?php

namespace App\Services\Video\Adapters;

class FastPixVideoPlatform extends AbstractStubVideoPlatform
{
    public function code(): string
    {
        return 'fastpix';
    }
}
