<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function complete(Request $request): JsonResponse
    {
        $request->user()->update(['tour_completed' => true]);

        return response()->json(['ok' => true]);
    }
}
