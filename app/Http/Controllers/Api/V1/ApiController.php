<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    use AuthorizesRequests;

    protected function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 20), 1), 100);
    }
}
