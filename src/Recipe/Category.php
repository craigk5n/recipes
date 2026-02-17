<?php

declare(strict_types=1);

namespace Recipes\Recipe;

/**
 * Standardized categories for recipes.
 */
enum Category: string
{
    case BREAKFAST = 'breakfast';
    case LUNCH = 'lunch';
    case DINNER = 'dinner';
    case DESSERT = 'dessert';
    case SNACK = 'snack';
    case APPETIZER = 'appetizer';
    case BEVERAGE = 'beverage';
    case SIDE = 'side';
    case MAIN = 'main';
    case BAKING = 'baking';
    case SOUP = 'soup';
    case SALAD = 'salad';
    case SAUCE = 'sauce';

    /**
     * Map strings to standardized Enum values.
     */
    public static function fromLegacy(string $value): ?self
    {
        $value = strtolower(trim($value));
        
        return match($value) {
            'breakfast' => self::BREAKFAST,
            'lunch' => self::LUNCH,
            'dinner', 'main dish', 'entree' => self::DINNER,
            'dessert', 'sweet', 'sweets' => self::DESSERT,
            'snack' => self::SNACK,
            'appetizer', 'starter' => self::APPETIZER,
            'beverage', 'drink', 'drinks' => self::BEVERAGE,
            'side', 'side dish' => self::SIDE,
            'main' => self::MAIN,
            'baking' => self::BAKING,
            'soup', 'stew' => self::SOUP,
            'salad' => self::SALAD,
            'sauce', 'condiment' => self::SAUCE,
            default => null,
        };
    }

    /**
     * Get label for display.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
