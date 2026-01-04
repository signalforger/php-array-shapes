<?php

namespace App\Action\Response;

/**
 * Salary range information.
 */
readonly class SalaryRangeDto implements \JsonSerializable
{
    public function __construct(
        public ?int $min,
        public ?int $max,
        public ?string $currency,
        public string $formatted,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'currency' => $this->currency,
            'formatted' => $this->formatted,
        ];
    }
}
