<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reflection extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'score', 'comment', 'scores'];

    protected $casts = [
        'scores' => 'array',
    ];

    // Assessor feedback left on this reflection (assessments.reflection_id)
    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }
}
