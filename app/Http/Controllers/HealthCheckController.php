<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'ok',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database connection failed',
            ], 500);
        }
    }
}
