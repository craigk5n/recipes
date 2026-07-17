<?php

include "rec_includes.php";

use function Recipes\I18n\t;
use function Recipes\Auth\getAuthManager;
use Recipes\Recipe\RecipeTextParser;

$auth = getAuthManager();
if (!$auth->can('edit')) {
    header("Location: login.php");
    exit;
}

$pagetitle = t('recipe.paste');
$error = '';
$parsedData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die_miserable_death("Invalid CSRF token.");
    }

    // "Edit Before Saving" action — store in session and redirect to edit.php
    if (isset($_POST['action']) && $_POST['action'] === 'edit_before_saving') {
        $_SESSION['import_data'] = json_decode($_POST['import_json'], true);
        header("Location: edit.php");
        exit;
    }

    // Parse pasted text
    $rawText = trim($_POST['recipe_text'] ?? '');
    if (empty($rawText)) {
        $error = 'Please paste some recipe text.';
    } else {
        $parser = new RecipeTextParser();
        $parsedData = $parser->parse($rawText);
        if (empty($parsedData['title'])) {
            $error = 'Could not detect a recipe title. Make sure the recipe title is on the first line.';
            $parsedData = null;
        }
    }
}

?>
<html>
<head>
<title><?php echo htmlspecialchars($pagetitle, ENT_QUOTES, 'UTF-8'); ?></title>
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
          <a class="nav-link" href="import.php"><?php echo t('recipe.import'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="paste.php"><?php echo t('recipe.paste'); ?></a>
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

<?php if ($parsedData !== null) { ?>

<!-- Preview -->
<div class="card">
<div class="card-header bg-success text-white">
  <h1 class="h4 mb-0">Recipe Preview</h1>
</div>
<div class="card-body">

<h3><?php echo htmlspecialchars($parsedData['title'], ENT_QUOTES, 'UTF-8'); ?></h3>

<?php if (!empty($parsedData['ingredients'])) { ?>
<h5><?php echo t('recipe.ingredients'); ?></h5>
<table class="table table-sm table-bordered mb-4">
  <thead class="table-light">
    <tr>
      <th style="width: 80px;">Qty</th>
      <th style="width: 120px;">Unit</th>
      <th>Ingredient</th>
      <th style="width: 150px;">Prep</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($parsedData['ingredients'] as $ingr) { ?>
    <tr>
      <td><?php echo htmlspecialchars($ingr['qty'], ENT_QUOTES, 'UTF-8'); ?></td>
      <td><?php echo htmlspecialchars($ingr['unit'], ENT_QUOTES, 'UTF-8'); ?></td>
      <td><?php echo htmlspecialchars($ingr['name'], ENT_QUOTES, 'UTF-8'); ?></td>
      <td><?php echo htmlspecialchars($ingr['prep'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
    <?php } ?>
  </tbody>
</table>
<?php } ?>

<?php if (!empty($parsedData['instructions'])) { ?>
<h5><?php echo t('recipe.instructions'); ?></h5>
<div class="card card-body bg-light mb-4">
  <?php echo nl2br(htmlspecialchars($parsedData['instructions'], ENT_QUOTES, 'UTF-8')); ?>
</div>
<?php } ?>

<hr>

<div class="d-flex gap-2 flex-wrap">
  <!-- Edit Before Saving -->
  <form method="post" action="paste.php">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="edit_before_saving">
    <input type="hidden" name="import_json" value="<?php echo htmlspecialchars(json_encode($parsedData), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" class="btn btn-primary">Edit Before Saving</button>
  </form>

  <!-- Start Over -->
  <a href="paste.php" class="btn btn-outline-secondary">Paste a Different Recipe</a>
</div>

</div>
</div>

<?php } else { ?>

<!-- Paste Form -->
<div class="card">
<div class="card-header bg-primary text-white">
  <h1 class="h4 mb-0"><?php echo t('recipe.paste'); ?></h1>
</div>
<div class="card-body">

<?php if (!empty($error)) { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>

<p><?php echo t('recipe.paste_description'); ?></p>

<form method="post" action="paste.php">
  <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
  <div class="mb-3">
    <label for="recipe_text" class="form-label">Recipe Text</label>
    <textarea class="form-control" id="recipe_text" name="recipe_text" rows="15"
              placeholder="Paste your recipe here..."
    ><?php echo htmlspecialchars($_POST['recipe_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary">Parse Recipe</button>
  <a href="index.php" class="btn btn-secondary"><?php echo t('navigation.cancel'); ?></a>
</form>

</div>
</div>

<?php } ?>

<?php include "trailer.php"; ?>
