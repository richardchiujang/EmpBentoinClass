<?php
namespace App\Services;

class MealService
{
    public static function calcSubtotal(int $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }
}
