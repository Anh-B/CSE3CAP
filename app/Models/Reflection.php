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

    protected static function booted(): void
    {
        // Delete evidence through the model (not just the database cascade)
        // so the uploaded files get removed from storage as well.
        static::deleting(function (Reflection $reflection) {
            $reflection->evidence()->get()->each->delete();
        });
    }

    // Assessor feedback left on this reflection (assessments.reflection_id)
    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    // Files and links the student attached as evidence
    public function evidence()
    {
        return $this->hasMany(Evidence::class);
    }
}
