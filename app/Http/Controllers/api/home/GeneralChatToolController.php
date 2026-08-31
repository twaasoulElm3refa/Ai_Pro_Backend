<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\GeneralTool\GenrealToolInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GeneralChatToolController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly GenrealToolInterface $genrealToolInterface)
    {

    }

    public function getAll()
    {
        $data=Cache::tags(['general_tools'])->remember(
            'general_tools',
            now()->addHours(72),
            function () {
                return $this->genrealToolInterface->getAll();
            }
        );
        return $this->success($data);
    }
}
