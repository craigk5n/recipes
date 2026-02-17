<?php

declare(strict_types=1);

namespace Recipes\Recipe;

/**
 * Standardized units for recipe ingredients.
 */
enum Unit: string
{
    case CUP = 'cup';
    case CUPS = 'cups';
    case TBSP = 'tablespoon';
    case TSP = 'teaspoon';
    case OZ = 'ounce';
    case LB = 'pound';
    case LBS = 'pounds';
    case G = 'gram';
    case KG = 'kilogram';
    case ML = 'milliliter';
    case L = 'liter';
    case PINCH = 'pinch';
    case DASH = 'dash';
    case PIECE = 'piece';
    case PIECES = 'pieces';
    case CLOVE = 'clove';
    case CLOVES = 'cloves';
    case STICK = 'stick';
    case STICKS = 'sticks';
    case PACKAGE = 'package';
    case BOX = 'box';
    case HEAD = 'head';

    /**
     * Map legacy or inconsistent strings to standardized Enum values.
     */
    public static function fromLegacy(string $value): ?self
    {
        $value = strtolower(trim($value));
        
        return match($value) {
            'cup', 'c' => self::CUP,
            'cups' => self::CUPS,
            'tablespoon', 'tbsp', 'tbs' => self::TBSP,
            'teaspoon', 'tsp' => self::TSP,
            'ounce', 'oz' => self::OZ,
            'pound', 'lb' => self::LB,
            'pounds', 'lbs' => self::LBS,
            'gram', 'g' => self::G,
            'kilogram', 'kg' => self::KG,
            'milliliter', 'ml' => self::ML,
            'liter', 'l' => self::L,
            'pinch' => self::PINCH,
            'dash' => self::DASH,
            'piece' => self::PIECE,
            'pieces' => self::PIECES,
            'clove' => self::CLOVE,
            'cloves' => self::CLOVES,
            'stick' => self::STICK,
            'sticks' => self::STICKS,
            'package', 'pkg' => self::PACKAGE,
            'box' => self::BOX,
            'head' => self::HEAD,
            default => null,
        };
    }

    /**
     * Get label for display.
     */
    public function label(): string
    {
        return match($this) {
            self::CUP => 'cup',
            self::CUPS => 'cups',
            self::TBSP => 'tablespoon',
            self::TSP => 'teaspoon',
            self::OZ => 'ounce',
            self::LB => 'pound',
            self::LBS => 'pounds',
            self::G => 'gram',
            self::KG => 'kilogram',
            self::ML => 'milliliter',
            self::L => 'liter',
            self::PINCH => 'pinch',
            self::DASH => 'dash',
            self::PIECE => 'piece',
            self::PIECES => 'pieces',
            self::CLOVE => 'clove',
            self::CLOVES => 'cloves',
            self::STICK => 'stick',
            self::STICKS => 'sticks',
            self::PACKAGE => 'package',
            self::BOX => 'box',
            self::HEAD => 'head',
        };
    }
}
