<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SattaKingFastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function index(Request $request, SattaKingFastService $api): JsonResponse
    {
        return response()->json($api->toResultsPayload());
    }
}
