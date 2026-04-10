<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $m = $request->user()->measurements()->latest()->first();
        if (! $m) {
            return response()->json(['message' => 'No measurements yet'], 404);
        }

        return response()->json($m);
    }

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->measurements()->latest()->paginate(20);

        return response()->json($items);
    }
}
