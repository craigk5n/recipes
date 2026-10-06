<?php

declare(strict_types=1);

namespace Recipes\Recipe;

/**
 * Parses raw pasted recipe text into structured fields.
 */
class RecipeTextParser
{
    /**
     * Unicode fraction map for normalization.
     * @var array<string, string>
     */
    private const FRACTION_MAP = [
        '½' => '1/2', '¼' => '1/4', '¾' => '3/4',
        '⅓' => '1/3', '⅔' => '2/3', '⅛' => '1/8',
        '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
    ];

    /**
     * Case-sensitive unit abbreviations (before lowercasing).
     * @var array<string, string>
     */
    private const CASE_SENSITIVE_UNITS = [
        'T' => 'tablespoon',
        't' => 'teaspoon',
        'C' => 'cup',
    ];

    /**
     * Known unit tokens recognized during parsing.
     * @var array<int, string>
     */
    private const UNIT_TOKENS = [
        'cup', 'cups', 'c',
        'tablespoon', 'tablespoons', 'tbsp', 'tbs',
        'teaspoon', 'teaspoons', 'tsp',
        'ounce', 'ounces', 'oz',
        'pound', 'pounds', 'lb', 'lbs',
        'gram', 'grams', 'g',
        'kilogram', 'kilograms', 'kg',
        'milliliter', 'milliliters', 'ml',
        'liter', 'liters', 'l',
        'pinch', 'dash',
        'piece', 'pieces',
        'clove', 'cloves',
        'stick', 'sticks',
        'package', 'packages', 'pkg',
        'box', 'boxes',
        'head', 'heads',
        'can', 'cans',
        'bunch', 'bunches',
        'sprig', 'sprigs',
        'slice', 'slices',
        'quart', 'quarts', 'qt',
        'pint', 'pints', 'pt',
        'gallon', 'gallons', 'gal',
        'fluid ounce', 'fluid ounces', 'fl oz', 'fl. oz',
    ];

    /** @var string */
    private const INGR_HEADINGS = '/^(ingredients?):?$/i';

    /** @var string */
    private const INSTR_HEADINGS = '/^(instructions?|directions?|steps?|method|preparation|procedure):?$/i';

    /**
     * Parse raw pasted recipe text into structured data.
     *
     * @return array{title: string, source: string, url: string, instructions: string, ingredients: list<array{qty: string, unit: string, name: string, prep: string}>}
     */
    public function parse(string $text): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $text));
        $lines = array_map('rtrim', $lines);

        $sections = $this->detectSections($lines);

        return [
            'title'        => $sections['title'],
            'source'       => '',
            'url'          => '',
            'instructions' => $sections['instructions'],
            'ingredients'  => array_map(
                [self::class, 'parseIngredientLine'],
                $sections['ingredientLines']
            ),
        ];
    }

    /**
     * Parse a single ingredient line into structured fields.
     *
     * @return array{qty: string, unit: string, name: string, prep: string}
     */
    public static function parseIngredientLine(string $line): array
    {
        $line = self::cleanIngredientText($line);
        if ($line === '') {
            return ['qty' => '', 'unit' => '', 'name' => '', 'prep' => ''];
        }

        // Handle case-sensitive unit abbreviations before any normalization.
        // The [TtC] character class only matches keys the map defines.
        $csPattern = '/^(\d+[\d\s\/\.]*?)\s+([TtC])\s+/';
        if (preg_match($csPattern, $line, $csMatch)) {
            $qty = trim($csMatch[1]);
            $remainder = substr($line, strlen($csMatch[0]));
            return self::splitNamePrep($qty, self::CASE_SENSITIVE_UNITS[$csMatch[2]], $remainder);
        }

        // Normalize Unicode fractions
        $line = strtr($line, self::FRACTION_MAP);
        // Fix digit immediately followed by fraction: "11/2" → "1 1/2"
        $line = preg_replace('/(\d)(\d\/\d)/', '$1 $2', $line);
        // "1 and 1/2" → "1 1/2"
        $line = preg_replace('/^(\d+)\s+and\s+(\d+\/\d+)/i', '$1 $2', $line);

        $qty = '';
        $remainder = $line;

        $unitPattern = implode('|', array_map('preg_quote', self::UNIT_TOKENS));

        // Extract leading quantity: "1 1/2", "1/2", "2", "2.5", "2-3", "1/4 - 1/3".
        // The unit may follow directly with no space, as in "250g".
        $number = '(?:\d+\s+\d+\/\d+|\d+\/\d+|\d+(?:\.\d+)?)';
        $qtyPattern = '/^(' . $number . ')(?:\s*-\s*(' . $number . '))?(?:\s+|(?=(?:' . $unitPattern . ')\b))/i';
        if (preg_match($qtyPattern, $line, $m)) {
            $qty = isset($m[2]) ? $m[1] . '-' . $m[2] : $m[1];
            $remainder = substr($line, strlen($m[0]));
        }

        // Try to match a unit token
        $unit = '';
        $altMeasure = '';
        if ($qty !== '') {
            if (preg_match('/^(' . $unitPattern . ')\.?\s+/i', $remainder, $m)) {
                $rawUnit = strtolower(trim($m[1]));
                $unitEnum = Unit::fromLegacy($rawUnit);
                $unit = $unitEnum !== null ? $unitEnum->value : $rawUnit;
                $remainder = substr($remainder, strlen($m[0]));

                // Alternate measure after a slash: "250g / 9oz cream cheese"
                $altPattern = '/^\/\s*(' . $number . '\s*(?:' . $unitPattern . ')\.?)\s+/i';
                if (preg_match($altPattern, $remainder, $m)) {
                    $altMeasure = $m[1];
                    $remainder = substr($remainder, strlen($m[0]));
                }
            }
        }

        $result = self::splitNamePrep($qty, $unit, $remainder);
        if ($altMeasure !== '') {
            $result['name'] = trim($result['name'] . ' (' . $altMeasure . ')');
        }
        return $result;
    }

    /**
     * Split remaining text into name and prep (after comma).
     *
     * @return array{qty: string, unit: string, name: string, prep: string}
     */
    private static function splitNamePrep(string $qty, string $unit, string $remainder): array
    {
        $name = trim($remainder);
        $prep = '';

        // Split at the first comma that is not inside parentheses, so
        // "eggs (large, room temperature)" stays whole.
        $comma = self::findTopLevelComma($name);
        if ($comma !== null) {
            $prep = trim(substr($name, $comma + 1));
            $name = trim(substr($name, 0, $comma));
        }

        return ['qty' => $qty, 'unit' => $unit, 'name' => $name, 'prep' => $prep];
    }

    /**
     * Byte offset of the first comma outside any parentheses, or null.
     */
    private static function findTopLevelComma(string $text): ?int
    {
        $depth = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')' && $depth > 0) {
                $depth--;
            } elseif ($c === ',' && $depth === 0 && $i > 0) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Normalize scraped ingredient text: decode HTML entities, unify
     * whitespace, drop list bullets, and tidy parenthesis artifacts that
     * recipe plugins leave behind ("((250g))", "()", "(, minced)").
     */
    public static function cleanIngredientText(string $line): string
    {
        $line = html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $line = str_replace("\u{00A0}", ' ', $line);
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        $line = preg_replace('/^[-*•–]\s+/u', '', $line);
        $line = preg_replace('/\(\(([^()]*)\)\)/', '($1)', $line);
        $line = preg_replace('/\(\s*,\s*/', '(', $line);
        $line = preg_replace('/\s*\(\s*\)/', '', $line);
        return trim($line);
    }

    /**
     * Convert a quantity string ("1 1/4", "1/2", "2.5", "½") to a float.
     *
     * Returns null for anything that isn't a single number, such as a
     * range ("2-3") or free text, so callers can keep it as text instead.
     */
    public static function quantityToFloat(string $qty): ?float
    {
        $qty = strtr(trim($qty), self::FRACTION_MAP);
        $qty = preg_replace('/(\d)(\d\/\d)/', '$1 $2', $qty);

        if (preg_match('/^\d+(?:\.\d+)?$/', $qty)) {
            return (float)$qty;
        }
        if (preg_match('/^(?:(\d+)\s+)?(\d+)\/(\d+)$/', $qty, $m)) {
            if ((int)$m[3] === 0) {
                return null;
            }
            return (float)$m[1] + (int)$m[2] / (int)$m[3];
        }
        return null;
    }

    /**
     * Detect sections from lines, trying heading-based first then structural inference.
     *
     * @param list<string> $lines
     * @return array{title: string, ingredientLines: list<string>, instructions: string}
     */
    private function detectSections(array $lines): array
    {
        $result = $this->detectSectionsFromHeadings($lines);
        if ($result !== null) {
            return $result;
        }
        return $this->inferSectionsFromStructure($lines);
    }

    /**
     * Attempt heading-based section detection.
     *
     * @param list<string> $lines
     * @return array{title: string, ingredientLines: list<string>, instructions: string}|null
     */
    private function detectSectionsFromHeadings(array $lines): ?array
    {
        $ingrIdx = null;
        $instrIdx = null;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            if ($ingrIdx === null && preg_match(self::INGR_HEADINGS, $trimmed)) {
                $ingrIdx = $i;
            } elseif ($instrIdx === null && preg_match(self::INSTR_HEADINGS, $trimmed)) {
                $instrIdx = $i;
            }
        }

        // Need at least one heading to use this method
        if ($ingrIdx === null && $instrIdx === null) {
            return null;
        }

        // Title: first non-empty line before the first heading
        $firstHeading = $ingrIdx ?? $instrIdx;
        $title = '';
        for ($i = 0; $i < $firstHeading; $i++) {
            $t = trim($lines[$i]);
            if ($t !== '') {
                $title = $t;
                break;
            }
        }

        if ($ingrIdx !== null && $instrIdx !== null) {
            // Both headings found
            $ingrLines = array_slice($lines, $ingrIdx + 1, $instrIdx - $ingrIdx - 1);
            $instrLines = array_slice($lines, $instrIdx + 1);
        } elseif ($ingrIdx !== null) {
            // Only ingredients heading — everything after is ingredients, no instructions
            $ingrLines = array_slice($lines, $ingrIdx + 1);
            $instrLines = [];
        } else {
            // Only instructions heading — everything before (after title) could be ingredients
            $ingrLines = array_slice($lines, ($title !== '' ? 1 : 0), $instrIdx - ($title !== '' ? 1 : 0));
            $instrLines = array_slice($lines, $instrIdx + 1);
        }

        $ingrLines = array_values(array_filter(array_map('trim', $ingrLines), fn($l) => $l !== ''));
        $instructions = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", array_map('trim', $instrLines))));

        return ['title' => $title, 'ingredientLines' => $ingrLines, 'instructions' => $instructions];
    }

    /**
     * Infer sections from text structure when no headings are present.
     *
     * Strategy: scan lines after the title. Collect consecutive ingredient-like
     * lines (starting with a number/fraction). Once we hit the first
     * non-ingredient line after at least one ingredient, everything from
     * that point on is instructions. Blank lines within the ingredient
     * block are skipped; blank lines are preserved in instructions.
     *
     * @param list<string> $lines
     * @return array{title: string, ingredientLines: list<string>, instructions: string}
     */
    private function inferSectionsFromStructure(array $lines): array
    {
        // Title = first non-empty line
        $title = '';
        $startIdx = 0;
        foreach ($lines as $i => $line) {
            if (trim($line) !== '') {
                $title = trim($line);
                $startIdx = $i + 1;
                break;
            }
        }

        $remaining = array_slice($lines, $startIdx);
        if (empty($remaining)) {
            return ['title' => $title, 'ingredientLines' => [], 'instructions' => ''];
        }

        // Line-by-line scan: collect ingredient lines, then switch to instructions
        $ingrLines = [];
        $instrStartIdx = null;
        $foundIngredient = false;

        foreach ($remaining as $i => $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                // Blank line: skip if we're still in ingredients
                if (!$foundIngredient) {
                    continue;
                }
                // If we've seen ingredients, a blank line doesn't end the section
                // (ingredients may have blank lines between groups)
                continue;
            }

            if ($this->looksLikeIngredient($trimmed)) {
                $foundIngredient = true;
                $ingrLines[] = $trimmed;
            } else {
                if ($foundIngredient) {
                    // First non-ingredient line after ingredients → instructions start
                    $instrStartIdx = $i;
                    break;
                }
                // Non-ingredient line before any ingredients — skip (description, etc.)
            }
        }

        // Build instructions from the remaining lines
        $instructions = '';
        if ($instrStartIdx !== null) {
            $instrLines = array_slice($remaining, $instrStartIdx);
            $instructions = trim(implode("\n", array_map('rtrim', $instrLines)));
            // Collapse 3+ consecutive blank lines to 2
            $instructions = preg_replace('/\n{3,}/', "\n\n", $instructions);
        }

        return ['title' => $title, 'ingredientLines' => $ingrLines, 'instructions' => $instructions];
    }

    /**
     * Heuristic: does this line look like a recipe ingredient?
     */
    private function looksLikeIngredient(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }

        // Normalize unicode fractions
        $line = strtr($line, self::FRACTION_MAP);

        // Starts with a digit (most common: "2 cups flour")
        if (preg_match('/^\d/', $line)) {
            return true;
        }

        return false;
    }
}
