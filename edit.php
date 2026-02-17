<?php

include "rec_includes.php";

use function Recipes\I18n\t;
use function Recipes\Auth\getAuthManager;
use Recipes\Database\Database;
use Recipes\Recipe\Unit;
use Recipes\Recipe\Category;

$auth = getAuthManager();
if (!$auth->can('edit')) {
    header("Location: login.php");
    exit;
}

$id = sanitizeInt($_GET['id'] ?? '');
$isEdit = !empty($id);

$recTitle = '';
$recSource = '';
$recUrl = '';
$instructions = '';
$ingredients = [];

if ($isEdit) {
  // Load recipe
  $res = Database::query("SELECT rec_id, rec_title, rec_last_updated, rec_source, rec_url, rec_category FROM rec_recipe WHERE rec_id = ?", [$id]);
  if (!$res) {
    die_miserable_death("Db error: " . Database::error());
  }
  $row = Database::fetchRow($res);
  if (!$row) {
    header("Location: index.php");
    exit;
  }
  $recTitle = $row[1];
  $recSource = $row[3];
  $recUrl = $row[4] ?? '';
  $recCategory = $row[5] ?? '';
  Database::freeResult($res);

  // Load ingredients
  $res = Database::query("SELECT rec_quantity, rec_quantity_type, rec_name, rec_prep FROM rec_ingr WHERE rec_id = ? ORDER BY rec_ingr_num", [$id]);
  if ($res) {
    while ($row = Database::fetchRow($res)) {
      $ingredients[] = [
        'qty' => $row[0],
        'unit' => $row[1],
        'name' => $row[2],
        'prep' => $row[3],
      ];
    }
    Database::freeResult($res);
  }

  // Load instructions
  $res = Database::query("SELECT rec_instructions FROM rec_instructions WHERE rec_id = ?", [$id]);
  if ($res && ($row = Database::fetchRow($res))) {
    $instructions = $row[0];
  }
  if ($res) Database::freeResult($res);

  $pagetitle = 'Edit Recipe: ' . htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8');
} else {
  // Check for import data in session
  if (isset($_SESSION['import_data'])) {
    $importData = $_SESSION['import_data'];
    unset($_SESSION['import_data']);
    $recTitle = $importData['title'] ?? '';
    $recSource = $importData['source'] ?? '';
    $recUrl = $importData['url'] ?? '';
    $instructions = $importData['instructions'] ?? '';
    if (!empty($importData['ingredients'])) {
      foreach ($importData['ingredients'] as $ingr) {
        $ingredients[] = [
          'qty' => '',
          'unit' => '',
          'name' => $ingr['raw'] ?? '',
          'prep' => '',
        ];
      }
    }
  }
  $pagetitle = 'Add Recipe';
  $recCategory = '';
}

// Ensure at least one empty ingredient row
if (empty($ingredients)) {
  $ingredients[] = ['qty' => '', 'unit' => '', 'name' => '', 'prep' => ''];
}

?>
<html>
<head>
<title><?php echo $pagetitle;?></title>
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
          <a class="nav-link <?php echo !$isEdit ? 'active' : ''; ?>" href="edit.php"><?php echo t('navigation.add_recipe'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="import.php"><?php echo t('recipe.import'); ?></a>
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

<div class="card">
<div class="card-header bg-primary text-white">
<h1 class="h4 mb-0"><?php echo $isEdit ? t('navigation.edit') . ' ' . t('recipe.title') : t('navigation.add_recipe'); ?></h1>
</div>
<div class="card-body">

<?php if (!$isEdit) { ?>
<div class="mb-3">
  <a href="import.php" class="btn btn-outline-secondary btn-sm">Import from URL</a>
</div>
<?php } ?>

<form action="edit_handler.php" method="post">
<input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
<?php if ($isEdit) { ?>
<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
<?php } ?>

<div class="mb-3">
  <label for="title" class="form-label"><?php echo t('recipe.title'); ?></label>
  <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8'); ?>" required>
</div>

<div class="mb-3">
  <label for="source" class="form-label"><?php echo t('recipe.source'); ?></label>
  <input type="text" class="form-control" id="source" name="source" value="<?php echo htmlspecialchars($recSource ?? '', ENT_QUOTES, 'UTF-8'); ?>">
</div>

<div class="mb-3">
  <label for="category" class="form-label"><?php echo t('recipe.category'); ?></label>
  <select class="form-select" id="category" name="category">
    <option value=""><?php echo t('navigation.filter'); ?>...</option>
    <?php foreach (Category::cases() as $cat) { ?>
      <option value="<?php echo $cat->value; ?>" <?php echo $recCategory === $cat->value ? 'selected' : ''; ?>>
        <?php echo $cat->label(); ?>
      </option>
    <?php } ?>
  </select>
</div>

<div class="mb-3">
  <label for="url" class="form-label">URL</label>
  <input type="url" class="form-control" id="url" name="url" value="<?php echo htmlspecialchars($recUrl ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://...">
</div>

<div class="mb-3">
  <label class="form-label"><?php echo t('recipe.ingredients'); ?></label>
  <div id="ingredients-container">
    <?php foreach ($ingredients as $i => $ingr) { ?>
    <div class="row mb-2 ingredient-row">
      <div class="col-2">
        <input type="text" class="form-control" name="qty[]" placeholder="Qty" value="<?php echo htmlspecialchars($ingr['qty'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-2">
        <input type="text" class="form-control" name="unit[]" list="units-list" placeholder="Unit" value="<?php echo htmlspecialchars($ingr['unit'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-3">
        <input type="text" class="form-control" name="ingredient[]" placeholder="Ingredient" value="<?php echo htmlspecialchars($ingr['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-3">
        <input type="text" class="form-control" name="prep[]" placeholder="Prep" value="<?php echo htmlspecialchars($ingr['prep'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      </div>
      <div class="col-2">
        <button type="button" class="btn btn-danger btn-remove-ingredient">Remove</button>
      </div>
    </div>
    <?php } ?>
  </div>
  <button type="button" class="btn btn-secondary btn-sm" id="btn-add-ingredient">+ Add Ingredient</button>
</div>

<div class="mb-3">
  <label for="instructions" class="form-label"><?php echo t('recipe.instructions'); ?></label>
  <textarea class="form-control" id="instructions" name="instructions" rows="8"><?php echo htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>

<button type="submit" class="btn btn-primary"><?php echo t('navigation.save'); ?></button>
<a href="<?php echo $isEdit ? 'view.php?id=' . (int)$id : 'index.php'; ?>" class="btn btn-secondary"><?php echo t('navigation.cancel'); ?></a>

</form>

<datalist id="units-list">
  <?php foreach (Unit::cases() as $unit) { ?>
    <option value="<?php echo $unit->value; ?>"><?php echo $unit->label(); ?></option>
  <?php } ?>
</datalist>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('btn-add-ingredient').addEventListener('click', function() {
        const container = document.getElementById('ingredients-container');
        const row = document.createElement('div');
        row.className = 'row mb-2 ingredient-row';
        row.innerHTML =
            '<div class="col-2"><input type="text" class="form-control" name="qty[]" placeholder="Qty"></div>' +
            '<div class="col-2"><input type="text" class="form-control" name="unit[]" list="units-list" placeholder="Unit"></div>' +
            '<div class="col-3"><input type="text" class="form-control" name="ingredient[]" placeholder="Ingredient"></div>' +
            '<div class="col-3"><input type="text" class="form-control" name="prep[]" placeholder="Prep"></div>' +
            '<div class="col-2"><button type="button" class="btn btn-danger btn-remove-ingredient">Remove</button></div>';
        container.appendChild(row);
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-ingredient')) {
            const btn = e.target.closest('.btn-remove-ingredient');
            const rows = document.querySelectorAll('.ingredient-row');
            if (rows.length > 1) {
                btn.closest('.ingredient-row').remove();
            }
        }
    });
});
</script>

<?php include "trailer.php"; ?>
