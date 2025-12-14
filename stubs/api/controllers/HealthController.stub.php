<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'API is running',
            'app' => config('app.name'),
            'environment' => config('app.env'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'time' => now()->toISOString(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
