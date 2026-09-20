<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CombinedResultsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function index(Request $request, CombinedResultsService $api): JsonResponse
    {
        return response()->json($api->toResultsPayload());
    }
}
