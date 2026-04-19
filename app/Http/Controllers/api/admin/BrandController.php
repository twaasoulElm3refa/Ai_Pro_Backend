<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Models\brands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    use apiResponse;

    private $cacheTime = 3600; // 1 ساعة

    /**
     * Get paginated brands with caching
     */
    public function index()
    {
        $page = request('page', 1);
        $cacheKey = "brands_index_page_$page";

        $brands = Cache::remember($cacheKey, $this->cacheTime, function () {
            return brands::latest()->paginate(6);
        });
        return $this->apiResponse($brands, 'All Brands');
    }

    public function all()
    {
        $brands = Cache::remember('all_brands', $this->cacheTime, function () {
            return brands::all();
        });
        return $this->apiResponse($brands, 'All Brands');
    }

    /**
     * Get total count of brands with caching
     */
    public function count()
    {
        $cacheKey = 'brands_count';

        $count = Cache::remember($cacheKey, $this->cacheTime, function () {
            return brands::count();
        });

        return $this->apiResponse($count, 'Brands Count');
    }

    /**
     * Show single brand with caching
     */
    public function show()
    {
        $id = request('id');
        $cacheKey = "brand_$id";

        $brand = Cache::remember($cacheKey, $this->cacheTime, function () use ($id) {
            return brands::find($id);
        });

        if (!$brand) {
            return $this->apiResponse([], 'Brand Not Found');
        }

        return $this->apiResponse($brand, 'Brand');
    }

    public function products()
    {
        $id = request('id');
        $brand = brands::find($id);
        if (!$brand) {
            return $this->apiResponse([], 'Brand Not Found');
        }
        return $this->apiResponse($brand->products, 'Brand Products');
    }

    /**
     * Create a new brand and clear relevant cache
     */
    public function create(BrandRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('brands', 'public');
            }
            $data['slug'] = $data['name'] . '-' . time();
            $brand = brands::create($data);
            $this->clearBrandCache();
            DB::commit();
            return $this->apiResponse($brand, 'Brand Created Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->apiResponse([], $th->getMessage());
        }

    }

    /**
     * Update a brand and clear relevant cache
     */
    public function update(Request $request)
    {
        $id = request('id');
        $brand = brands::find($id);

        if (!$brand) {
            return $this->apiResponse([], 'Brand Not Found');
        }

        $data = $request->all();
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('brands');
        }

        $brand->update($data);

        $this->clearBrandCache($id);

        return $this->apiResponse($brand, 'Brand Updated Successfully');
    }

    /**
     * Delete a brand and clear relevant cache
     */
    public function destroy()
    {
        $id = request('id');
        $brand = brands::find($id);

        if (!$brand) {
            return $this->apiResponse([], 'Brand Not Found');
        }

        $brand->delete();

        $this->clearBrandCache($id);

        return $this->apiResponse([], 'Brand Deleted Successfully');
    }

    /**
     * Clear all relevant cache keys
     */
    private function clearBrandCache($id = null)
    {
        // Clear count cache
        Cache::forget('brands_count');

        // Clear paginated pages cache
        $pages = ceil(brands::count() / 10);
        for ($i = 1; $i <= max(1, $pages); $i++) {
            Cache::forget("brands_index_page_$i");
        }

        // Clear single brand cache if id provided
        if ($id) {
            Cache::forget("brand_$id");
        }
    }
}
