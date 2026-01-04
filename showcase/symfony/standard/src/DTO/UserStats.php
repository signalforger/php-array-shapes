<?php

namespace App\DTO;

/**
 * User Statistics DTO with userland type validation.
 */
readonly class UserStats
{
    /**
     * @param array<string, int> $byGender
     * @param array<string, int> $byCountry
     * @param array<string, int> $byAgeGroup
     */
    public function __construct(
        public int $total,
        public array $byGender,
        public array $byCountry,
        public array $byAgeGroup,
        public float $averageAge,
    ) {}

    public static function fromUsers(array $users): self
    {
        $byGender = [];
        $byCountry = [];
        $byAgeGroup = [
            '18-25' => 0,
            '26-35' => 0,
            '36-45' => 0,
            '46-55' => 0,
            '56+' => 0,
        ];
        $totalAge = 0;

        foreach ($users as $user) {
            $gender = $user['gender'] ?? 'unknown';
            $byGender[$gender] = ($byGender[$gender] ?? 0) + 1;

            $country = $user['nat'] ?? 'unknown';
            $byCountry[$country] = ($byCountry[$country] ?? 0) + 1;

            $age = $user['dob']['age'] ?? 0;
            $totalAge += $age;

            if ($age <= 25) {
                $byAgeGroup['18-25']++;
            } elseif ($age <= 35) {
                $byAgeGroup['26-35']++;
            } elseif ($age <= 45) {
                $byAgeGroup['36-45']++;
            } elseif ($age <= 55) {
                $byAgeGroup['46-55']++;
            } else {
                $byAgeGroup['56+']++;
            }
        }

        $count = count($users);

        return new self(
            total: $count,
            byGender: $byGender,
            byCountry: $byCountry,
            byAgeGroup: $byAgeGroup,
            averageAge: $count > 0 ? $totalAge / $count : 0.0,
        );
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'by_gender' => $this->byGender,
            'by_country' => $this->byCountry,
            'by_age_group' => $this->byAgeGroup,
            'average_age' => $this->averageAge,
        ];
    }
}
