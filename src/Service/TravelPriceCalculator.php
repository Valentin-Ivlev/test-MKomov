<?php

namespace App\Service;

use Symfony\Component\Validator\Constraints as Assert;

class TravelPriceCalculator
{
    public function getValidationConstraints(): Assert\Collection
    {
        return new Assert\Collection([
            'price' => [new Assert\NotBlank(), new Assert\Type(['type' => 'numeric']), new Assert\PositiveOrZero()],
            'startDate' => [new Assert\NotBlank(), new Assert\Date()],
            'paymentDate' => [new Assert\NotBlank(), new Assert\Date()],
            'age' => [new Assert\NotBlank(), new Assert\Type(['type' => 'integer']), new Assert\PositiveOrZero()],
        ]);
    }

    public function calculate(array $data): array
    {
        $price = $data['price'];
        $startDate = new \DateTimeImmutable($data['startDate']);
        $paymentDate = new \DateTimeImmutable($data['paymentDate']);
        $age = $data['age'];

        if ($paymentDate > $startDate) {
            throw new \InvalidArgumentException('Дата платежа не может быть позднее даты начала путешествия');
        }

        $childDiscount = $this->calculateChildDiscount($price, $age);
        $discountedPrice = $price - $childDiscount;

        $earlyBookingDiscount = $this->calculateEarlyBookingDiscount($discountedPrice, $startDate, $paymentDate);
        $finalPrice = $discountedPrice - $earlyBookingDiscount;

        return [
            'price' => $price,
            'childDiscount' => $childDiscount,
            'earlyBookingDiscount' => $earlyBookingDiscount,
            'finalPrice' => $finalPrice,
        ];
    }

    private function calculateChildDiscount(float $price, int $age): float
    {
        return match (true) {
            $age < 3 => 0,
            $age < 6 => $price * 0.8,
            $age < 12 => min($price * 0.3, 4500),
            $age < 18 => $price * 0.1,
            default => 0,
        };
    }

    private function calculateEarlyBookingDiscount(float $price, \DateTimeImmutable $startDate, \DateTimeImmutable $paymentDate): float
    {
        $year = $startDate->format('Y');
        $month = $startDate->format('m');

        return match (true) {
            $month >= 4 && $month <= 9 => $this->calculateDiscountForSummerSeason($price, $year, $paymentDate),
            ($month >= 10 && $month <= 12) || ($month == 1 && $startDate->format('d') <= 14) => $this->calculateDiscountForWinterSeason($price, $year, $paymentDate),
            default => $this->calculateDiscountForOtherSeasons($price, $year, $paymentDate),
        };
    }

    private function calculateDiscountForSummerSeason(float $price, int $year, \DateTimeImmutable $paymentDate): float
    {
        return match (true) {
            $paymentDate <= new \DateTimeImmutable("$year-11-30") => min($price * 0.07, 1500),
            $paymentDate <= new \DateTimeImmutable("$year-12-31") => min($price * 0.05, 1500),
            $paymentDate <= new \DateTimeImmutable(($year + 1) . "-01-31") => min($price * 0.03, 1500),
            default => 0,
        };
    }

    private function calculateDiscountForWinterSeason(float $price, int $year, \DateTimeImmutable $paymentDate): float
    {
        $prevYear = $year - 1;

        return match (true) {
            $paymentDate <= new \DateTimeImmutable("$prevYear-03-31") => min($price * 0.07, 1500),
            $paymentDate <= new \DateTimeImmutable("$prevYear-04-30") => min($price * 0.05, 1500),
            $paymentDate <= new \DateTimeImmutable("$prevYear-05-31") => min($price * 0.03, 1500),
            default => 0,
        };
    }

    private function calculateDiscountForOtherSeasons(float $price, int $year, \DateTimeImmutable $paymentDate): float
    {
        $prevYear = $year - 1;

        return match (true) {
            $paymentDate <= new \DateTimeImmutable("$prevYear-08-31") => min($price * 0.07, 1500),
            $paymentDate <= new \DateTimeImmutable("$prevYear-09-30") => min($price * 0.05, 1500),
            $paymentDate <= new \DateTimeImmutable("$prevYear-10-31") => min($price * 0.03, 1500),
            default => 0,
        };
    }
}