<?php

include "rec_includes.php";

use function Recipes\I18n\t;
use function Recipes\Auth\getAuthManager;

$auth = getAuthManager();
if (!$auth->can('edit')) {
    header("Location: login.php");
    exit;
}

require_once "includes/recipe_import.php";

$pagetitle = 'Import Recipe';
$error = '';
$recipeData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Rate limit: 5 imports per hour
  enforceRateLimit('import', 5, 3600);

  if (isset($_POST['action']) && $_POST['action'] === 'edit_before_saving') {
    // Store data in session and redirect to edit.php
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
      die_miserable_death("Invalid CSRF token.");
    }
    $_SESSION['import_data'] = json_decode($_POST['import_json'], true);
    header("Location: edit.php");
    exit;
  }

  // Import from URL
  $url = trim($_POST['url'] ?? '');
  if (empty($url)) {
    $error = 'Please enter a URL.';
  } else {
    $result = importRecipeFromUrl($url);
    $recipeData = $result['data'];
    if ($recipeData === null) {
      $errorCode = $result['error'];
      switch ($errorCode) {
        case 'invalid_url':
          $error = 'The URL provided is not valid. Please enter a full URL starting with http:// or https://.';
          break;
        case 'fetch_failed':
          $error = 'Could not fetch the page. The site may be down, blocking automated requests, or the URL may be incorrect.';
          break;
        case 'no_jsonld':
          $error = 'No structured recipe data found on this page. This site does not use JSON-LD markup.'
            . ' You can try copying the recipe text and using the <a href="paste.php">Paste Recipe</a> feature instead.';
          break;
        case 'no_recipe_type':
          $foundTypes = $result['found_types'] ?? [];
          $typesStr = !empty($foundTypes) ? ' (found: ' . htmlspecialchars(implode(', ', array_unique($foundTypes)), ENT_QUOTES, 'UTF-8') . ')' : '';
          $error = 'This page has structured data but it is not marked as a Recipe' . $typesStr . '.'
            . ' Try copying the recipe text and using the <a href="paste.php">Paste Recipe</a> feature instead.';
          break;
        default:
          $error = 'Could not import this recipe. Try using the <a href="paste.php">Paste Recipe</a> feature instead.';
          break;
      }
    }
  }
}

?>
<html>
<head>
<title><?php echo $pagetitle; ?></title>
<?php include "includes/styles.php"; ?>
<?php include "includes/js.php"; ?>
<?php require_once "../style.css"; ?>
</head>
<body style="margin: 0">
<?php
require_once "../header.php";
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Recipes</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#recipesNavbar" aria-controls="recipesNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="recipesNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><?php echo t('navigation.home'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="edit.php"><?php echo t('navigation.add_recipe'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="import.php"><?php echo t('recipe.import'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="paste.php"><?php echo t('recipe.paste'); ?></a>
        </li>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if ($auth->getMode() === 'pin') { ?>
          <li class="nav-item"><a class="nav-link" href="auth_handler.php?action=logout" title="Lock">🔓</a></li>
        <?php } elseif ($auth->getMode() === 'user') { ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?php echo htmlspecialchars($auth->getCurrentUser()['username']); ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
              <li><a class="dropdown-menu" href="auth_handler.php?action=logout">Logout</a></li>
            </ul>
          </li>
        <?php } ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">

<?php if ($recipeData !== null) { ?>

<!-- Preview -->
<div class="card">
<div class="card-header bg-success text-white">
  <h1 class="h4 mb-0">Recipe Found — Preview</h1>
</div>
<div class="card-body">

<?php if (!empty($recipeData['image_url'])) { ?>
<div class="text-center mb-4">
  <img src="<?php echo htmlspecialchars($recipeData['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
       alt="Recipe photo" class="img-fluid rounded" style="max-height: 350px; object-fit: contain;">
</div>
<?php } ?>

<h3><?php echo htmlspecialchars($recipeData['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></h3>

<?php if (!empty($recipeData['description'])) { ?>
<p class="text-muted"><?php echo htmlspecialchars($recipeData['description'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<div class="row mb-3">
  <?php if (!empty($recipeData['source'])) { ?>
  <div class="col-auto"><strong>Author:</strong> <?php echo htmlspecialchars($recipeData['source'], ENT_QUOTES, 'UTF-8'); ?></div>
  <?php } ?>
  <?php if (!empty($recipeData['yield'])) { ?>
  <div class="col-auto"><strong>Yield:</strong> <?php echo htmlspecialchars($recipeData['yield'], ENT_QUOTES, 'UTF-8'); ?></div>
  <?php } ?>
</div>

<?php
$times = [];
if (!empty($recipeData['prep_time'])) $times[] = 'Prep: ' . $recipeData['prep_time'];
if (!empty($recipeData['cook_time'])) $times[] = 'Cook: ' . $recipeData['cook_time'];
if (!empty($recipeData['total_time'])) $times[] = 'Total: ' . $recipeData['total_time'];
if (!empty($times)) { ?>
<p><strong>Time:</strong> <?php echo htmlspecialchars(implode(' | ', $times), ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<?php if (!empty($recipeData['ingredients'])) { ?>
<h5>Ingredients</h5>
<ul class="list-group list-group-flush mb-4">
  <?php foreach ($recipeData['ingredients'] as $ingr) { ?>
  <li class="list-group-item"><?php echo htmlspecialchars($ingr['raw'], ENT_QUOTES, 'UTF-8'); ?></li>
  <?php } ?>
</ul>
<?php } ?>

<?php if (!empty($recipeData['instructions'])) { ?>
<h5>Instructions</h5>
<div class="card card-body bg-light mb-4">
  <?php echo nl2br(htmlspecialchars($recipeData['instructions'], ENT_QUOTES, 'UTF-8')); ?>
</div>
<?php } ?>

<?php
$meta = [];
if (!empty($recipeData['category'])) $meta[] = 'Category: ' . $recipeData['category'];
if (!empty($recipeData['cuisine'])) $meta[] = 'Cuisine: ' . $recipeData['cuisine'];
if (!empty($recipeData['keywords'])) $meta[] = 'Keywords: ' . $recipeData['keywords'];
if (!empty($meta)) { ?>
<p class="text-muted small"><?php echo htmlspecialchars(implode(' | ', $meta), ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<p class="text-muted small">Source URL: <a href="<?php echo htmlspecialchars($recipeData['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($recipeData['url'], ENT_QUOTES, 'UTF-8'); ?></a></p>

<hr>

<div class="d-flex gap-2 flex-wrap">
  <!-- Save Recipe directly -->
  <form method="post" action="edit_handler.php">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="title" value="<?php echo htmlspecialchars($recipeData['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="source" value="<?php echo htmlspecialchars($recipeData['source'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="url" value="<?php echo htmlspecialchars($recipeData['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="image_url" value="<?php echo htmlspecialchars($recipeData['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="instructions" value="<?php echo htmlspecialchars($recipeData['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($recipeData['ingredients'])) {
      foreach ($recipeData['ingredients'] as $ingr) { ?>
    <input type="hidden" name="qty[]" value="">
    <input type="hidden" name="unit[]" value="">
    <input type="hidden" name="ingredient[]" value="<?php echo htmlspecialchars($ingr['raw'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="prep[]" value="">
    <?php }
    } ?>
    <button type="submit" class="btn btn-primary">Save Recipe</button>
  </form>

  <!-- Edit Before Saving -->
  <form method="post" action="import.php">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="edit_before_saving">
    <input type="hidden" name="import_json" value="<?php echo htmlspecialchars(json_encode($recipeData), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" class="btn btn-secondary">Edit Before Saving</button>
  </form>

  <!-- Start Over -->
  <a href="import.php" class="btn btn-outline-secondary">Import a Different URL</a>
</div>

</div>
</div>

<?php } else { ?>

<!-- URL Input Form -->
<div class="card">
<div class="card-header bg-primary text-white">
  <h1 class="h4 mb-0"><?php echo t('recipe.import_from_url'); ?></h1>
</div>
<div class="card-body">

<?php if (!empty($error)) { ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php } ?>

<p>Enter the URL of a recipe page. The recipe data will be extracted automatically from sites that use structured data (most major recipe sites).</p>

<form method="post" action="import.php">
  <div class="mb-3">
    <label for="url" class="form-label">Recipe URL</label>
    <input type="url" class="form-control" id="url" name="url"
           placeholder="https://www.allrecipes.com/recipe/..."
           value="<?php echo htmlspecialchars($_POST['url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
  </div>
  <button type="submit" class="btn btn-primary"><?php echo t('navigation.search'); ?></button>
  <a href="index.php" class="btn btn-secondary"><?php echo t('navigation.cancel'); ?></a>
</form>

</div>
</div>

<?php } ?>

<?php include "trailer.php"; ?>
