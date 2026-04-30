<?php

namespace App\Http\Controllers\api\admin\tools;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ToolRequest;
use App\Http\Requests\ToolUpdateRequest;
use App\Jobs\TranslateToolJob;
use App\Repository\tools\MainToolInterface;
use App\Services\SeoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MainToolController extends Controller
{
    use ApiResponse;

    private $toolRepository;

    public function __construct(MainToolInterface $toolRepository)
    {
        $this->toolRepository = $toolRepository;
    }

    public function index()
    {
        try {
            $tools = $this->toolRepository->index();
            return $this->success($tools, 'Tools fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Index Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function show($id)
    {
        try {
            $tool = $this->toolRepository->show($id);

            return $this->success($tool, 'Tool fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Show Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function store(ToolRequest $request)
    {
        try {
            $data = $request->validated();
            if (request()->hasFile('image')) {
                $data['image'] = $request->file('image')->store('tools', 'public');
            }
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(6);
            $seo = app(SeoService::class)->generateMeta(
                $data['name'] ?? '',
                $data['description'] ?? ''
            );
            $data = array_merge($data, $seo);
            $tool = $this->toolRepository->store($data);
            TranslateToolJob::dispatch($tool->id);
            return $this->success($tool, 'Tool created successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Store Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function update(ToolUpdateRequest $request, $id)
    {
        try {
            $data = $request->validated();
            if (request()->hasFile('image')) {
                $data['image'] = $request->file('image')->store('tools', 'public');
            }
            $tool = $this->toolRepository->update($data, $id);

            return $this->success($tool, 'Tool updated successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Update Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $this->toolRepository->destroy($id);

            return $this->success(null, 'Tool deleted successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Destroy Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

}
