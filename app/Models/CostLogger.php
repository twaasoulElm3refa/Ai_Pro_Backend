<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLogger extends Model
{
    protected $table = 'cost_loggers';

    protected $guarded = [];

    protected $casts = [
        'total_cost' => 'decimal:4',
        'input_cost' => 'decimal:4',
        'output_cost' => 'decimal:4',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subTool()
    {
        return $this->belongsTo(SubTools::class, 'sub_tool_id');
    }
}
