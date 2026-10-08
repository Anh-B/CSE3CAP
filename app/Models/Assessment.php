<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = ['reflection_id', 'assessor_id', 'score', 'feedback', 'scores'];

    protected $casts = [
        'scores' => 'array',
    ];

    // The assessor who wrote this assessment
    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    // The reflection this assessment is scoring
    public function reflection()
    {
        return $this->belongsTo(Reflection::class);
    }
}
