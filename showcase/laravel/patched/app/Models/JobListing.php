<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobListing extends Model
{
    protected $fillable = [
        'external_id',
        'source',
        'title',
        'company_id',
        'company_name',
        'company_logo',
        'location',
        'remote',
        'job_type',
        'salary_min',
        'salary_max',
        'salary_currency',
        'description',
        'url',
        'tags',
        'posted_at',
        'fetched_at',
    ];

    protected $casts = [
        'remote' => 'boolean',
        'salary_min' => 'integer',
        'salary_max' => 'integer',
        'tags' => 'array',
        'posted_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get formatted salary range.
     * Returns a type-safe array shape (runtime validated).
     */
    public function getSalaryRange(): array{min: ?int, max: ?int, currency: ?string, formatted: string}
    {
        $formatted = 'Not specified';

        if ($this->salary_min || $this->salary_max) {
            $currency = $this->salary_currency ?? 'USD';
            if ($this->salary_min && $this->salary_max) {
                $formatted = sprintf('%s %s - %s', $currency, number_format($this->salary_min), number_format($this->salary_max));
            } elseif ($this->salary_min) {
                $formatted = sprintf('%s %s+', $currency, number_format($this->salary_min));
            } else {
                $formatted = sprintf('Up to %s %s', $currency, number_format($this->salary_max));
            }
        }

        return [
            'min' => $this->salary_min,
            'max' => $this->salary_max,
            'currency' => $this->salary_currency,
            'formatted' => $formatted,
        ];
    }
}
