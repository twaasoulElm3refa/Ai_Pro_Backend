<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MainToolTranlation extends Model
{
    protected $table = 'main_tool_tranlations';
    protected $guarded = [];

    public function mainTool()
    {
        return $this->belongsTo(MainTools::class,'main_tool_id');
    }
}
