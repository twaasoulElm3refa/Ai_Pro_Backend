<?php

namespace App\Http\Controllers\api\admin;


trait apiResponse
{
    public function apiResponse($data = null, $message = '')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public function sendError($error)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        return response()->json($response, 404);
    }

    public function unauthorized($message = 'Unauthorized Request')
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], 401);
    }
}