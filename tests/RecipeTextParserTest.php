<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Recipes\Recipe\RecipeTextParser;

class RecipeTextParserTest extends TestCase
{
    private RecipeTextParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RecipeTextParser();
    }

    // --- parseIngredientLine tests ---

    public function testParseIngredientWithQtyUnitName(): void
    {
        $result = RecipeTextParser::parseIngredientLine('6 cups tomato meat sauce');
        $this->assertEquals('6', $result['qty']);
        $this->assertEquals('cups', $result['unit']);
        $this->assertEquals('tomato meat sauce', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientWithPrep(): void
    {
        $result = RecipeTextParser::parseIngredientLine('2 cups mozzarella cheese, shredded');
        $this->assertEquals('2', $result['qty']);
        $this->assertEquals('cups', $result['unit']);
        $this->assertEquals('mozzarella cheese', $result['name']);
        $this->assertEquals('shredded', $result['prep']);
    }

    public function testParseIngredientNoUnit(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1 large eggplant');
        $this->assertEquals('1', $result['qty']);
        $this->assertEquals('', $result['unit']);
        $this->assertEquals('large eggplant', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientMixedFraction(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1 1/2 cups milk');
        $this->assertEquals('1 1/2', $result['qty']);
        $this->assertEquals('cups', $result['unit']);
        $this->assertEquals('milk', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientSimpleFraction(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1/2 cup butter');
        $this->assertEquals('1/2', $result['qty']);
        $this->assertEquals('cup', $result['unit']);
        $this->assertEquals('butter', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientUnicodeFraction(): void
    {
        $result = RecipeTextParser::parseIngredientLine('½ cup butter');
        $this->assertEquals('1/2', $result['qty']);
        $this->assertEquals('cup', $result['unit']);
        $this->assertEquals('butter', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientUnicodeMixedFraction(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1½ cups sugar');
        $this->assertEquals('1 1/2', $result['qty']);
        $this->assertEquals('cups', $result['unit']);
        $this->assertEquals('sugar', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientCaseSensitiveT(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1 T dried oregano');
        $this->assertEquals('1', $result['qty']);
        $this->assertEquals('tablespoon', $result['unit']);
        $this->assertEquals('dried oregano', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientCaseSensitiveLowercaseT(): void
    {
        $result = RecipeTextParser::parseIngredientLine('1 t vanilla extract');
        $this->assertEquals('1', $result['qty']);
        $this->assertEquals('teaspoon', $result['unit']);
        $this->assertEquals('vanilla extract', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientDecimalQty(): void
    {
        $result = RecipeTextParser::parseIngredientLine('2.5 oz cream cheese');
        $this->assertEquals('2.5', $result['qty']);
        $this->assertEquals('ounce', $result['unit']);
        $this->assertEquals('cream cheese', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientRangeQty(): void
    {
        $result = RecipeTextParser::parseIngredientLine('2-3 cloves garlic');
        $this->assertEquals('2-3', $result['qty']);
        $this->assertEquals('cloves', $result['unit']);
        $this->assertEquals('garlic', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientNoQty(): void
    {
        $result = RecipeTextParser::parseIngredientLine('Salt and pepper to taste');
        $this->assertEquals('', $result['qty']);
        $this->assertEquals('', $result['unit']);
        $this->assertEquals('Salt and pepper to taste', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientEmptyLine(): void
    {
        $result = RecipeTextParser::parseIngredientLine('');
        $this->assertEquals('', $result['qty']);
        $this->assertEquals('', $result['unit']);
        $this->assertEquals('', $result['name']);
        $this->assertEquals('', $result['prep']);
    }

    public function testParseIngredientBasilWithPrep(): void
    {
        $result = RecipeTextParser::parseIngredientLine('8 small fresh basil leaves, chopped');
        $this->assertEquals('8', $result['qty']);
        $this->assertEquals('', $result['unit']);
        $this->assertEquals('small fresh basil leaves', $result['name']);
        $this->assertEquals('chopped', $result['prep']);
    }

    // --- Full recipe parse tests ---

    public function testParseWithHeadings(): void
    {
        $text = "Eggplant Lasagna\n\nIngredients\n1 large eggplant\n2 cups tomato sauce\n\nInstructions\nPreheat oven to 400 degrees.";
        $result = $this->parser->parse($text);

        $this->assertEquals('Eggplant Lasagna', $result['title']);
        $this->assertCount(2, $result['ingredients']);
        $this->assertEquals('1', $result['ingredients'][0]['qty']);
        $this->assertEquals('large eggplant', $result['ingredients'][0]['name']);
        $this->assertEquals('2', $result['ingredients'][1]['qty']);
        $this->assertEquals('cups', $result['ingredients'][1]['unit']);
        $this->assertStringContainsString('Preheat oven to 400', $result['instructions']);
    }

    public function testParseWithHeadingsAndColons(): void
    {
        $text = "My Recipe\n\nIngredients:\n1 cup flour\n2 eggs\n\nDirections:\nMix together.";
        $result = $this->parser->parse($text);

        $this->assertEquals('My Recipe', $result['title']);
        $this->assertCount(2, $result['ingredients']);
        $this->assertStringContainsString('Mix together', $result['instructions']);
    }

    public function testParseWithoutHeadings(): void
    {
        $text = "Eggplant Lasagna\n\n1 large eggplant\n6 cups tomato meat sauce\n2 cups ricotta cheese\n\nPreheat oven 400 degrees. Line a baking sheet with aluminum foil.";
        $result = $this->parser->parse($text);

        $this->assertEquals('Eggplant Lasagna', $result['title']);
        $this->assertCount(3, $result['ingredients']);
        $this->assertEquals('1', $result['ingredients'][0]['qty']);
        $this->assertEquals('large eggplant', $result['ingredients'][0]['name']);
        $this->assertStringContainsString('Preheat', $result['instructions']);
    }

    public function testParseExampleRecipe(): void
    {
        $text = <<<'RECIPE'
Eggplant lasagna

1 large eggplant
6 cups tomato meat sauce
2 cups ricotta cheese
2 cups mozzarella cheese, shredded
1 cup parmesan cheese, shredded
8 small fresh basil leaves, chopped
1 T dried oregano

Preheat over 400 degrees. Line a baking sheet with aluminum foil.

Cut ends off eggplants and slice lengthwise in 1/4" slices. You should end up with about 10 slices total.

Brush each slice of eggplant with olive oil on both sides and sprinkle with a little salt & pepper. Bake about 7 min each side.

In a large mixing bowl, combine ricotta, parmesan and basil. Mix until all ingredients are well incorporated.

Pour 2 cups of sauce into the bottom of an 9x13. Spread into an even layer. On top of the sauce, layer half of the eggplant slices.
Layer on half of the ricotta mixture, spreading evenly across the entire dish. Sprinkle 1/2 cup of mozzarella cheese on top of the ricotta
mixture.
Pour 2 cups of meat sauce on top of the cheese and spread in an even layer across the entire dish. Layer the remaining eggplant on top
of the sauce.
Layer second half of the ricotta mixture, spreading evenly across the entire dish. Sprinkle 1/2 cup mozzarella on top of the ricotta mixture.
Pour remaining 2 cups of meat sauce on top of the cheese and spread in an even layer across the entire dish.
Sprinkle remaining 1 cup of mozzarella cheese evenly across the entire dish. Sprinkle oregano on top of cheese. Cover with foil and bake
30 min. Remove foil and broil on high 5-8 min. Let stand 15 min before cutting.
RECIPE;

        $result = $this->parser->parse($text);

        // Title
        $this->assertEquals('Eggplant lasagna', $result['title']);

        // Should detect 7 ingredients
        $this->assertCount(7, $result['ingredients']);

        // Check specific ingredients
        $this->assertEquals('1', $result['ingredients'][0]['qty']);
        $this->assertEquals('', $result['ingredients'][0]['unit']);
        $this->assertEquals('large eggplant', $result['ingredients'][0]['name']);

        $this->assertEquals('6', $result['ingredients'][1]['qty']);
        $this->assertEquals('cups', $result['ingredients'][1]['unit']);
        $this->assertEquals('tomato meat sauce', $result['ingredients'][1]['name']);

        $this->assertEquals('2', $result['ingredients'][3]['qty']);
        $this->assertEquals('cups', $result['ingredients'][3]['unit']);
        $this->assertEquals('mozzarella cheese', $result['ingredients'][3]['name']);
        $this->assertEquals('shredded', $result['ingredients'][3]['prep']);

        $this->assertEquals('1', $result['ingredients'][6]['qty']);
        $this->assertEquals('tablespoon', $result['ingredients'][6]['unit']);
        $this->assertEquals('dried oregano', $result['ingredients'][6]['name']);

        // Instructions should start with "Preheat"
        $this->assertStringStartsWith('Preheat', $result['instructions']);
        $this->assertStringContainsString('Let stand 15 min before cutting.', $result['instructions']);

        // Source/URL should be empty
        $this->assertEquals('', $result['source']);
        $this->assertEquals('', $result['url']);
    }

    public function testParseEmptyText(): void
    {
        $result = $this->parser->parse('');
        $this->assertEquals('', $result['title']);
        $this->assertEmpty($result['ingredients']);
        $this->assertEquals('', $result['instructions']);
    }

    public function testParseTitleOnly(): void
    {
        $result = $this->parser->parse('Just a Title');
        $this->assertEquals('Just a Title', $result['title']);
        $this->assertEmpty($result['ingredients']);
        $this->assertEquals('', $result['instructions']);
    }
}
