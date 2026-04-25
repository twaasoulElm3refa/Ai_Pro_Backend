<?php

namespace App\Http\Controllers\api\admin\tools;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubToolController extends Controller
{
    use ApiResponse;

    private $toolRepository;

    public function __construct(SubToolInterface $toolRepository)
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
                'trace' => $th->getTraceAsString(),
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
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function store(SubToolRequest $request)
    {
        try {
            $data = $request->validated();
            if (request()->hasFile('image')) {
                $data['image'] = $request->file('image')->store('tools', 'public');
            }
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(6);
            $tool = $this->toolRepository->store($data);

            return $this->success($tool, 'Tool created successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Store Error', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function update(SubToolUpdateRequest $request, $id)
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
                'trace' => $th->getTraceAsString(),
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
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error('Something went wrong.');
        }
    }
}
