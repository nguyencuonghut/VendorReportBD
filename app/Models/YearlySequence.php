<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearlySequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'current_seq',
    ];

    protected $casts = [
        'year' => 'integer',
        'current_seq' => 'integer',
    ];
}
