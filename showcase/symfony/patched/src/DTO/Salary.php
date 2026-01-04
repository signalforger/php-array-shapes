<?php

/**
 * Salary DTO
 *
 * Represents salary information with formatting methods.
 * DTOs contain behavior (methods) unlike shapes which are pure data structures.
 */

namespace App\DTO;

readonly class Salary
{
    public function __construct(
        public ?int $min,
        public ?int $max,
        public ?string $currency,
    ) {}

    /**
     * Create from a shape-validated array
     */
    public static function fromShape(array $data): self
    {
        return new self(
            min: $data['min'] ?? null,
            max: $data['max'] ?? null,
            currency: $data['currency'] ?? null,
        );
    }

    /**
     * Format salary for display
     */
    public function formatted(): string
    {
        if ($this->min === null && $this->max === null) {
            return 'Not specified';
        }

        $currency = $this->currency ?? 'USD';

        if ($this->min !== null && $this->max !== null) {
            return sprintf('%s %s - %s', $currency, number_format($this->min), number_format($this->max));
        }

        if ($this->min !== null) {
            return sprintf('%s %s+', $currency, number_format($this->min));
        }

        return sprintf('Up to %s %s', $currency, number_format($this->max));
    }

    /**
     * Check if salary is specified
     */
    public function isSpecified(): bool
    {
        return $this->min !== null || $this->max !== null;
    }

    /**
     * Get midpoint salary (for sorting/comparison)
     */
    public function midpoint(): ?int
    {
        if ($this->min !== null && $this->max !== null) {
            return (int) (($this->min + $this->max) / 2);
        }

        return $this->min ?? $this->max;
    }

    /**
     * Check if salary meets minimum threshold
     */
    public function meetsMinimum(int $threshold): bool
    {
        if ($this->min !== null) {
            return $this->min >= $threshold;
        }

        if ($this->max !== null) {
            return $this->max >= $threshold;
        }

        return false;
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'currency' => $this->currency,
            'formatted' => $this->formatted(),
        ];
    }
}
