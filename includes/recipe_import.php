<?php

/**
 * Import a recipe from a URL by parsing JSON-LD schema.org Recipe data.
 *
 * @param string $url The URL to fetch and parse
 * @return array{data: array|null, error: string|null} Result with recipe data or error message
 */
function importRecipeFromUrl($url)
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['data' => null, 'error' => 'invalid_url'];
    }

    $html = _fetchUrl($url);
    if ($html === null) {
        return ['data' => null, 'error' => 'fetch_failed'];
    }

    $extraction = _extractRecipeFromHtml($html);
    if ($extraction['recipe'] === null) {
        return ['data' => null, 'error' => $extraction['reason']];
    }

    return ['data' => _normalizeRecipe($extraction['recipe'], $url), 'error' => null];
}

/**
 * Fetch URL content via curl.
 */
function _fetchUrl($url)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_ENCODING       => '',  // accept any encoding
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($html === false || $httpCode < 200 || $httpCode >= 400) {
        return null;
    }

    return $html;
}

/**
 * Parse HTML and extract Recipe object from JSON-LD script tags.
 *
 * @return array{recipe: array|null, reason: string} Extraction result with reason on failure
 */
function _extractRecipeFromHtml($html)
{
  // Suppress DOMDocument warnings for malformed HTML
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
    libxml_use_internal_errors($prev);

    $scripts = $doc->getElementsByTagName('script');
    $candidates = [];
    $hasJsonLd = false;
    $foundTypes = [];

    foreach ($scripts as $script) {
        if ($script->getAttribute('type') !== 'application/ld+json') {
            continue;
        }

        $json = trim($script->textContent);
        if (empty($json)) {
            continue;
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            continue;
        }

        $hasJsonLd = true;
        _collectTypes($data, $foundTypes);

        $found = _findRecipesInData($data);
        foreach ($found as $recipe) {
            $candidates[] = $recipe;
        }
    }

    if (empty($candidates)) {
        if (!$hasJsonLd) {
            return ['recipe' => null, 'reason' => 'no_jsonld'];
        }
        return ['recipe' => null, 'reason' => 'no_recipe_type', 'found_types' => $foundTypes];
    }

  // Pick the most complete recipe (most non-null relevant fields)
    $best = null;
    $bestScore = -1;
    $scoreFields = ['name', 'recipeIngredient', 'recipeInstructions', 'image', 'author', 'description'];

    foreach ($candidates as $c) {
        $score = 0;
        foreach ($scoreFields as $f) {
            if (!empty($c[$f])) {
                $score++;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $c;
        }
    }

    return ['recipe' => $best, 'reason' => 'ok'];
}

/**
 * Collect @type values from a JSON-LD structure for diagnostic purposes.
 */
function _collectTypes($data, &$types)
{
    if (!is_array($data)) {
        return;
    }

    if (isset($data['@type'])) {
        $t = $data['@type'];
        if (is_string($t)) {
            $types[] = $t;
        } elseif (is_array($t)) {
            foreach ($t as $v) {
                if (is_string($v)) {
                    $types[] = $v;
                }
            }
        }
    }

    if (isset($data['@graph']) && is_array($data['@graph'])) {
        foreach ($data['@graph'] as $item) {
            _collectTypes($item, $types);
        }
    }

    if (array_is_list($data)) {
        foreach ($data as $item) {
            _collectTypes($item, $types);
        }
    }
}

/**
 * Recursively search data structure for Recipe objects.
 */
function _findRecipesInData($data)
{
    $results = [];

    if (!is_array($data)) {
        return $results;
    }

  // Check if this object itself is a Recipe
    if (_isRecipeType($data)) {
        $results[] = $data;
        return $results;
    }

  // Check @graph array
    if (isset($data['@graph']) && is_array($data['@graph'])) {
        foreach ($data['@graph'] as $item) {
            if (is_array($item) && _isRecipeType($item)) {
                $results[] = $item;
            }
        }
        if (!empty($results)) {
            return $results;
        }
    }

  // Recurse into numerically indexed arrays (e.g. top-level array of objects)
    if (array_is_list($data)) {
        foreach ($data as $item) {
            $results = array_merge($results, _findRecipesInData($item));
        }
    }

    return $results;
}

/**
 * Check if a data array has @type = Recipe.
 */
function _isRecipeType($data)
{
    if (!isset($data['@type'])) {
        return false;
    }

    $type = $data['@type'];

    if (is_string($type)) {
        return $type === 'Recipe' || str_ends_with($type, '/Recipe');
    }

    if (is_array($type)) {
        foreach ($type as $t) {
            if ($t === 'Recipe' || (is_string($t) && str_ends_with($t, '/Recipe'))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Normalize a raw Recipe JSON-LD object into a clean array.
 */
function _normalizeRecipe($recipe, $sourceUrl)
{
    return [
    'title'        => _getString($recipe, 'name'),
    'description'  => _getString($recipe, 'description'),
    'source'       => _extractAuthor($recipe['author'] ?? null),
    'url'          => $sourceUrl,
    'image_url'    => _extractImage($recipe['image'] ?? null, $sourceUrl),
    'yield'        => _extractYield($recipe['recipeYield'] ?? null),
    'prep_time'    => _formatIsoDuration($recipe['prepTime'] ?? null),
    'cook_time'    => _formatIsoDuration($recipe['cookTime'] ?? null),
    'total_time'   => _formatIsoDuration($recipe['totalTime'] ?? null),
    'ingredients'  => _extractIngredients($recipe['recipeIngredient'] ?? null),
    'instructions' => _extractInstructions($recipe['recipeInstructions'] ?? null),
    'keywords'     => _extractKeywords($recipe['keywords'] ?? null),
    'category'     => _extractStringOrFirst($recipe['recipeCategory'] ?? null),
    'cuisine'      => _extractStringOrFirst($recipe['recipeCuisine'] ?? null),
    ];
}

/**
 * Safely get a string field.
 */
function _getString($data, $key)
{
    if (!isset($data[$key])) {
        return null;
    }
    $val = $data[$key];
    if (is_string($val)) {
        return trim($val);
    }
    if (is_array($val) && isset($val[0]) && is_string($val[0])) {
        return trim($val[0]);
    }
    return null;
}

/**
 * Extract author name from various formats.
 */
function _extractAuthor($author)
{
    if ($author === null) {
        return null;
    }

    if (is_string($author)) {
        return trim($author);
    }

    if (is_array($author)) {
      // Single author object: {"@type": "Person", "name": "..."}
        if (isset($author['name'])) {
            return trim($author['name']);
        }
      // Array of author objects
        if (isset($author[0])) {
            $first = $author[0];
            if (is_string($first)) {
                return trim($first);
            }
            if (is_array($first) && isset($first['name'])) {
                return trim($first['name']);
            }
        }
    }

    return null;
}

/**
 * Extract the best image URL from various formats.
 */
function _extractImage($image, $baseUrl)
{
    if ($image === null) {
        return null;
    }

    $url = null;

    if (is_string($image)) {
        $url = $image;
    } elseif (is_array($image)) {
        if (isset($image['url'])) {
          // Single ImageObject
            $url = $image['url'];
        } elseif (isset($image[0])) {
          // Array of images — pick the first string or object
            $first = $image[0];
            if (is_string($first)) {
                $url = $first;
            } elseif (is_array($first) && isset($first['url'])) {
                $url = $first['url'];
            }
        }
    }

    if ($url === null || !is_string($url)) {
        return null;
    }

    $url = trim($url);

  // Make relative URLs absolute
    if (!preg_match('#^https?://#i', $url)) {
        $parts = parse_url($baseUrl);
        $base = $parts['scheme'] . '://' . $parts['host'];
        if ($url[0] === '/') {
            $url = $base . $url;
        } else {
            $path = isset($parts['path']) ? dirname($parts['path']) : '';
            $url = $base . $path . '/' . $url;
        }
    }

    return $url;
}

/**
 * Extract yield as a string.
 */
function _extractYield($yield)
{
    if ($yield === null) {
        return null;
    }
    if (is_string($yield)) {
        return trim($yield);
    }
    if (is_numeric($yield)) {
        return (string)$yield;
    }
    if (is_array($yield) && isset($yield[0])) {
        return is_string($yield[0]) ? trim($yield[0]) : (string)$yield[0];
    }
    return null;
}

/**
 * Extract ingredients array.
 */
function _extractIngredients($ingredients)
{
    if (!is_array($ingredients)) {
        return [];
    }

    $result = [];
    foreach ($ingredients as $item) {
        if (is_string($item)) {
            $text = trim($item);
            if ($text !== '') {
                $result[] = ['raw' => $text];
            }
        }
    }

    return $result;
}

/**
 * Extract instructions text from various formats.
 */
function _extractInstructions($instructions)
{
    if ($instructions === null) {
        return '';
    }

    if (is_string($instructions)) {
      // Strip HTML tags that some sites embed
        return trim(strip_tags($instructions));
    }

    if (!is_array($instructions)) {
        return '';
    }

    $steps = [];

    foreach ($instructions as $item) {
        if (is_string($item)) {
            $steps[] = trim(strip_tags($item));
        } elseif (is_array($item)) {
            $type = $item['@type'] ?? '';

            if ($type === 'HowToStep') {
                $text = $item['text'] ?? $item['name'] ?? '';
                if (is_string($text) && trim($text) !== '') {
                    $steps[] = trim(strip_tags($text));
                }
            } elseif ($type === 'HowToSection') {
              // Section has a name and itemListElement with HowToStep items
                $sectionName = $item['name'] ?? '';
                if (is_string($sectionName) && trim($sectionName) !== '') {
                    $steps[] = trim($sectionName) . ':';
                }
                $subItems = $item['itemListElement'] ?? [];
                if (is_array($subItems)) {
                    foreach ($subItems as $sub) {
                        if (is_string($sub)) {
                              $steps[] = trim(strip_tags($sub));
                        } elseif (is_array($sub)) {
                            $text = $sub['text'] ?? $sub['name'] ?? '';
                            if (is_string($text) && trim($text) !== '') {
                                $steps[] = trim(strip_tags($text));
                            }
                        }
                    }
                }
            }
        }
    }

    return implode("\n\n", $steps);
}

/**
 * Convert ISO 8601 duration (e.g. "PT1H30M") to readable string.
 */
function _formatIsoDuration($iso)
{
    if ($iso === null || !is_string($iso)) {
        return null;
    }

    $iso = trim($iso);
    if ($iso === '' || $iso === 'P' || $iso === 'PT') {
        return null;
    }

    try {
        $interval = new DateInterval($iso);
    } catch (Exception $e) {
        return null;
    }

    $parts = [];
    if ($interval->d > 0) {
        $parts[] = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
    if ($interval->h > 0) {
        $parts[] = $interval->h . ' hr' . ($interval->h > 1 ? 's' : '');
    }
    if ($interval->i > 0) {
        $parts[] = $interval->i . ' min';
    }

    return !empty($parts) ? implode(' ', $parts) : null;
}

/**
 * Extract keywords as a comma-separated string.
 */
function _extractKeywords($keywords)
{
    if ($keywords === null) {
        return null;
    }
    if (is_string($keywords)) {
        return trim($keywords);
    }
    if (is_array($keywords)) {
        $filtered = array_filter(array_map('trim', $keywords), function ($v) {
            return $v !== '';
        });
        return !empty($filtered) ? implode(', ', $filtered) : null;
    }
    return null;
}

/**
 * Extract a string or the first element of an array.
 */
function _extractStringOrFirst($val)
{
    if ($val === null) {
        return null;
    }
    if (is_string($val)) {
        return trim($val);
    }
    if (is_array($val) && isset($val[0]) && is_string($val[0])) {
        return trim($val[0]);
    }
    return null;
}
