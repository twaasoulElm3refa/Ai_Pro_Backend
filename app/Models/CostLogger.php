<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLogger extends Model
{
    protected $table = 'cost_loggers';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id');
    }

    public function subTool()
    {
        return $this->belongsTo(SubTools::class ,'sub_tool_id');
    }
}
