<?php

namespace App\Services\Video\Adapters;

class VimeoVideoPlatform extends AbstractStubVideoPlatform
{
    public function code(): string
    {
        return 'vimeo';
    }
}
