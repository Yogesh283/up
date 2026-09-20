<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SattaMatkaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function index(Request $request, SattaMatkaApi $api): JsonResponse
    {
        $board = $api->board($request->query('date'));
        $payload = $api->toResultsPayload($board);

        return response()->json($payload);
    }
}
