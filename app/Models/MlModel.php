<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'version', 'algorithm', 'features', 'training_period',
        'validation_period', 'test_period', 'metrics', 'model_path', 'status',
    ];

    protected $casts = [
        'features' => 'array',
        'metrics' => 'array',
    ];
}
