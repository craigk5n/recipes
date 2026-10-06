<?php

include "rec_includes.php";

use Recipes\Database\Database;
use Recipes\Http\SafeHttpClient;
use Recipes\Media\ImageProcessor;
use function Recipes\Auth\getAuthManager;
use Recipes\Recipe\Category;
use Recipes\Recipe\RecipeTextParser;
use Recipes\Recipe\Unit;
use Recipes\Security\Security;

$auth = getAuthManager();
if (!$auth->can('edit')) { // Global check for any edit permission (e.g., admin)
    die_miserable_death("Unauthorized.");
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  die_miserable_death("Invalid CSRF token.");
}

$id = sanitizeInt($_POST['id'] ?? '');
$isNew = empty($id);

// Specific ownership check for non-admins editing existing recipes
if (!$isNew && !$auth->can('edit', (int)$id)) {
    die_miserable_death("Unauthorized: You do not own this recipe.");
}

// Stored as plain text; every page escapes these on output. Escaping here too
// made titles like "M&M" show up as "M&amp;M" and grow on every re-save.
$recTitle = trim($_POST['title'] ?? '');
$recSource = trim($_POST['source'] ?? '');
$recUrl = trim($_POST['url'] ?? '');
$recCategory = trim($_POST['category'] ?? '');

if ($recUrl !== '' && !Security::isHttpUrl($recUrl)) {
  die_miserable_death("Error: URL must start with http:// or https://.");
}
$instructions = trim($_POST['instructions'] ?? '');

if (empty($recTitle)) {
  die_miserable_death("Error: Title is required.");
}

// Validate category against Enum
$categoryEnum = Category::tryFrom($recCategory);
$finalCategory = $categoryEnum ? $categoryEnum->value : null;

$now = date('Y-m-d H:i:s');

Database::beginTransaction();

try {
  // Check if user_id column exists (auth migration may not have been run)
  $currentUser = $auth->getCurrentUser();
  $userId = $currentUser ? $currentUser['id'] : null;
  $hasUserIdCol = false;
  $colCheck = Database::query("SHOW COLUMNS FROM rec_recipe LIKE 'user_id'");
  if ($colCheck && Database::fetchRow($colCheck)) {
    $hasUserIdCol = true;
  }
  if ($colCheck) {
    Database::freeResult($colCheck);
  }

  if ($isNew) {
    // Get next ID
    $res = Database::query("SELECT MAX(rec_id) FROM rec_recipe");
    $row = Database::fetchRow($res);
    $id = ($row && $row[0]) ? $row[0] + 1 : 1;
    Database::freeResult($res);

    // Insert recipe
    if ($hasUserIdCol) {
      Database::query(
        "INSERT INTO rec_recipe (rec_id, rec_title, rec_last_updated, rec_date_added, rec_source, rec_url, rec_category, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $recTitle, $now, $now, $recSource, $recUrl, $finalCategory, $userId]
      );
    } else {
      Database::query(
        "INSERT INTO rec_recipe (rec_id, rec_title, rec_last_updated, rec_date_added, rec_source, rec_url, rec_category) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$id, $recTitle, $now, $now, $recSource, $recUrl, $finalCategory]
      );
    }

    // Clean up any orphaned data from a previously deleted recipe with this ID
    Database::query("DELETE FROM rec_ingr WHERE rec_id = ?", [$id]);
    Database::query("DELETE FROM rec_instructions WHERE rec_id = ?", [$id]);
  } else {
    // Update recipe
    if ($hasUserIdCol) {
      Database::query(
        "UPDATE rec_recipe SET rec_title = ?, rec_last_updated = ?, rec_source = ?, rec_url = ?, rec_category = ? WHERE rec_id = ? AND user_id = ?",
        [$recTitle, $now, $recSource, $recUrl, $finalCategory, $id, $userId]
      );
    } else {
      Database::query(
        "UPDATE rec_recipe SET rec_title = ?, rec_last_updated = ?, rec_source = ?, rec_url = ?, rec_category = ? WHERE rec_id = ?",
        [$recTitle, $now, $recSource, $recUrl, $finalCategory, $id]
      );
    }

    // Delete existing ingredients and instructions (will reinsert)
    Database::query("DELETE FROM rec_ingr WHERE rec_id = ?", [$id]);
    Database::query("DELETE FROM rec_instructions WHERE rec_id = ?", [$id]);
  }

  // Insert ingredients
  $qtys = $_POST['qty'] ?? [];
  $units = $_POST['unit'] ?? [];
  $names = $_POST['ingredient'] ?? [];
  $preps = $_POST['prep'] ?? [];

  $ingrNum = 1;
  for ($i = 0; $i < count($names); $i++) {
    $name = trim($names[$i] ?? '');
    if (empty($name)) continue;

    $qty = trim($qtys[$i] ?? '');
    $unitRaw = trim($units[$i] ?? '');
    $prep = trim($preps[$i] ?? '');

    // Standardize unit via Enum
    $unitEnum = Unit::fromLegacy($unitRaw);
    $unit = $unitEnum ? $unitEnum->value : $unitRaw;

    // rec_quantity is FLOAT and strict mode rejects "1/2" or "1 1/4", so
    // convert to a number. Anything that isn't one number (a range like
    // "2-3") stays readable by moving it to the front of the name.
    $qtyParam = null;
    if ($qty !== '') {
      $qtyParam = RecipeTextParser::quantityToFloat($qty);
      if ($qtyParam === null) {
        $name = trim("$qty $unit $name");
        $unit = '';
      }
    }

    Database::query(
      "INSERT INTO rec_ingr (rec_id, rec_ingr_num, rec_quantity, rec_quantity_type, rec_name, rec_prep) VALUES (?, ?, ?, ?, ?, ?)",
      [$id, $ingrNum, $qtyParam, $unit, $name, $prep]
    );
    $ingrNum++;
  }

  // Insert instructions
  if (!empty($instructions)) {
    Database::query(
      "INSERT INTO rec_instructions (rec_id, rec_instructions) VALUES (?, ?)",
      [$id, $instructions]
    );
  }

  Database::commit();
} catch (Exception $e) {
  Database::rollback();
  die_miserable_death("Error saving recipe: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// If an image URL was provided (from import), download and store it as a photo
$imageUrl = trim($_POST['image_url'] ?? '');
if (!empty($imageUrl) && Security::isHttpUrl($imageUrl)) {
  $response = (new SafeHttpClient())->fetch($imageUrl, 10 * 1024 * 1024);
  $photo = $response !== null ? ImageProcessor::normalize($response['body']) : null;
  if ($photo !== null) {
    $originalName = basename((string)parse_url($imageUrl, PHP_URL_PATH)) ?: 'imported.jpg';
    Database::query(
      "INSERT INTO rec_photo (rec_id, photo_data, original_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?, 1)",
      [$id, $photo['data'], $originalName, $photo['mime'], strlen($photo['data'])]
    );
  }
}

header("Location: view.php?id=" . (int)$id);
exit;

?>
