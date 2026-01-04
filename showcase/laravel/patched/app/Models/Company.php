<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'website',
        'description',
        'industry',
        'size',
        'founded_year',
        'enriched_at',
    ];

    protected $casts = [
        'enriched_at' => 'datetime',
        'founded_year' => 'integer',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(JobListing::class);
    }
}
