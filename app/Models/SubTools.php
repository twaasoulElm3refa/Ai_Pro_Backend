<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubTools extends Model
{
    use SoftDeletes;

    protected $table = 'sub_tools';

    protected $guarded = [];

    public function mainTools()
    {
        return $this->belongsTo(MainTools::class, 'main_tool_id');
    }

    public function conversation()
    {
        return $this->hasMany(Conversation::class, 'sub_tool_id');
    }

    public function translations()
    {
        return $this->hasMany(SubToolTranlation::class, 'sub_tool_id');
    }

    public function translation()
    {
        return $this->hasOne(SubToolTranlation::class, 'sub_tool_id')
            ->where('locale', app()->getLocale());
    }

    public function provider()
    {
        return $this->hasOne(Provider::class, 'sub_tool_id');
    }

    public function cost()
    {
        return $this->hasMany(CostLogger::class, 'sub_tool_id');
    }
}
