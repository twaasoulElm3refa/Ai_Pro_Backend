<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Products;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    use apiResponse;
    private $cacheTime = 600;

    public function index()
    {
        $page = request('page', 1);
        $cacheKey = "products_page_{$page}";
        $products = Cache::remember($cacheKey, $this->cacheTime, function () {
            return Products::paginate(10);
        });
        return $this->apiResponse($products, 'All products');
    }

    public function count()
    {
        $count = Products::count();
        return $this->apiResponse($count, 'Products Count');
    }

    public function show()
    {
        $product = Products::with('category', 'brand')->find(request('id'));
        if (!$product) {
            return $this->apiResponse([], 'Product Not Found');
        }
        return $this->apiResponse($product, 'Product');
    }

    public function create(ProductRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
             if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('products', 'public');
            }
            $data['slug'] = $data['name'] . '-' . time();
            $product = Products::create($data);
            DB::commit();
            $this->clearProductsCache();
            return $this->apiResponse($product, 'Product Created Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->apiResponse([], $th->getMessage());
        }
    }

    public function update(ProductRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('products', 'public');
            }
            $data['slug'] = $data['name'] . '-' . time();
            $product = Products::find(request('id'));
            $product->update($data);
            DB::commit();
            $this->clearProductsCache();
            return $this->apiResponse($product, 'Product Updated Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->apiResponse([], $th->getMessage());
        }
    }

    public function destroy()
    {
        $product = Products::find(request('id'));
        if (!$product) {
            return $this->apiResponse([], 'Product Not Found');
        }
        $product->delete();
        $this->clearProductsCache();
        return $this->apiResponse([], 'Product Deleted Successfully');
    }


    private function clearProductsCache()
    {
        for ($i = 1; $i <= 50; $i++) {
            Cache::forget("products_page_$i");
        }
    }

}
