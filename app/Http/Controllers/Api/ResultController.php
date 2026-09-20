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
        $payload = $api->toResultsPayload($request->query('date'));

        return response()->json($payload);
    }
}
