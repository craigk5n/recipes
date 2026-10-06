<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/recipe_import.php';

/**
 * Exercises the JSON-LD import pipeline (extract + normalize) without network access.
 * Ingredient and instruction strings are taken from real recipe sites.
 */
class RecipeImportTest extends TestCase
{
    /**
     * Wrap a Recipe object in a minimal HTML page with a JSON-LD script tag.
     *
     * @param array<string, mixed> $recipe
     */
    private static function htmlWithRecipe(array $recipe): string
    {
        $recipe = ['@context' => 'https://schema.org', '@type' => 'Recipe'] + $recipe;
        $json = json_encode(['@graph' => [['@type' => 'WebPage'], $recipe]], JSON_UNESCAPED_UNICODE);
        return '<html><head><script type="application/ld+json">' . $json . '</script></head><body></body></html>';
    }

    /**
     * @param array<string, mixed> $recipe
     * @return array<string, mixed>
     */
    private static function import(array $recipe): array
    {
        $extraction = _extractRecipeFromHtml(self::htmlWithRecipe($recipe));
        self::assertNotNull($extraction['recipe']);
        return _normalizeRecipe($extraction['recipe'], 'https://example.com/recipe/');
    }

    public function testIngredientsAreSplitIntoQtyUnitNamePrep(): void
    {
        $data = self::import([
            'name' => 'Easy Pumpkin Cupcakes',
            'recipeIngredient' => [
                '1 ¼ cup all purpose flour  (150g)',
                '1 tsp baking powder',
                '2  eggs (large, room temperature)',
                '2 cups frozen mixed vegetables, thawed',
                'Salt and pepper (to taste)',
            ],
        ]);

        $fields = array_map(
            fn($i) => [$i['qty'], $i['unit'], $i['name'], $i['prep']],
            $data['ingredients']
        );
        $this->assertSame([
            ['1 1/4', 'cup', 'all purpose flour (150g)', ''],
            ['1', 'teaspoon', 'baking powder', ''],
            ['2', '', 'eggs (large, room temperature)', ''],
            ['2', 'cups', 'frozen mixed vegetables', 'thawed'],
            ['', '', 'Salt and pepper (to taste)', ''],
        ], $fields);
    }

    public function testIngredientKeepsRawText(): void
    {
        $data = self::import(['name' => 'X', 'recipeIngredient' => ['1 tsp baking powder']]);
        $this->assertSame('1 tsp baking powder', $data['ingredients'][0]['raw']);
    }

    public function testInstructionStepsKeepCommas(): void
    {
        $step = 'In a medium bowl combine the 1 ¼ cup flour, 1 cup brown sugar, 1 tsp baking powder, '
            . '½ tsp baking soda, ½ tsp kosher salt, and ½ tsp ground cinnamon and mix well with a spatula '
            . 'to combine, making sure to break up the brown sugar clumps.';
        $data = self::import([
            'name' => 'X',
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Preheat the oven to 350°F and line a muffin tin with 12 muffin liners.'],
                ['@type' => 'HowToStep', 'text' => $step],
            ],
        ]);

        $this->assertSame(
            "Preheat the oven to 350°F and line a muffin tin with 12 muffin liners.\n\n" . $step,
            $data['instructions']
        );
    }

    public function testInstructionHtmlStringKeepsCommasAndParagraphs(): void
    {
        $data = self::import([
            'name' => 'X',
            'recipeInstructions' => '<p>Melt butter, sugar, and salt.</p><p>Stir in flour,&nbsp;then bake.</p>',
        ]);

        $this->assertSame("Melt butter, sugar, and salt.\n\nStir in flour, then bake.", $data['instructions']);
    }

    public function testHtmlEntitiesAreDecoded(): void
    {
        $data = self::import([
            'name' => 'Brown Butter M&amp;M Cookies',
            'recipeIngredient' => ['1/2  cup warm water ((make sure it&#x27;s not too hot))'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Don&#39;t overmix&nbsp;the batter &amp; chill.'],
            ],
        ]);

        $this->assertSame('Brown Butter M&M Cookies', $data['title']);
        $this->assertSame("warm water (make sure it's not too hot)", $data['ingredients'][0]['name']);
        $this->assertSame("Don't overmix the batter & chill.", $data['instructions']);
    }

    public function testHowToSectionsKeepHeadingsAndCommas(): void
    {
        $data = self::import([
            'name' => 'X',
            'recipeInstructions' => [
                [
                    '@type' => 'HowToSection',
                    'name' => 'Frosting',
                    'itemListElement' => [
                        ['@type' => 'HowToStep', 'text' => 'Beat butter, cream cheese, and vanilla.'],
                    ],
                ],
            ],
        ]);

        $this->assertSame("Frosting:\n\nBeat butter, cream cheese, and vanilla.", $data['instructions']);
    }
}
