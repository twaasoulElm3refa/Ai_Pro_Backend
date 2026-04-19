<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubToolTranlation extends Model
{
    protected $table = 'sub_tool_tranlations';
    protected $guarded = [];

    public function subTool()
    {
        return $this->belongsTo(SubTools::class,'sub_tool_id');
    }
}
