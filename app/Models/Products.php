<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    /** @use HasFactory<\Database\Factories\ProductsFactory> */
    use HasFactory;

    protected $table = 'products';
    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];


    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Categories::class, 'categories_id');
    }

    public function brand()
    {
        return $this->belongsTo(brands::class, 'brands_id');
    }
}
