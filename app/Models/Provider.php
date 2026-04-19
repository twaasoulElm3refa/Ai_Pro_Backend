<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $table = 'providers';
    protected $guarded = [];

    public function subTools()
    {
        return $this->belongsTo(SubTools::class,'sub_tool_id');
    }
}
