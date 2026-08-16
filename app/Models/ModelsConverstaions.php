<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelsConverstaions extends Model
{
    protected $table = 'models_converstaions';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function model()
    {
        return $this->belongsTo(MainFreeAiModels::class,'model_id');
    }

}
