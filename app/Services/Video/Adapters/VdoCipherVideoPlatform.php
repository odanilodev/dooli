<?php

namespace App\Services\Video\Adapters;

class VdoCipherVideoPlatform extends AbstractStubVideoPlatform
{
    public function code(): string
    {
        return 'vdocipher';
    }
}
