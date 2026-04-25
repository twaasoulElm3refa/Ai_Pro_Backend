<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainTools extends Model
{
    use SoftDeletes;
    protected $table = 'main_tools';
    protected $guarded = [];

    public function subTools()
    {
        return $this->hasMany(SubTools::class,'main_tool_id');
    }

    public function translations()
    {
        return $this->hasMany(MainToolTranlation::class,'main_tools_id');
    }

    public function translation()
    {
        return $this->hasOne(MainToolTranlation::class,'main_tools_id')
               ->where('locale',app()->getLocale());
    }
}
