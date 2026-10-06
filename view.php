<?php

include "rec_includes.php";

use function Recipes\I18n\t;
use function Recipes\Auth\getAuthManager;
use Recipes\Database\Database;
use Recipes\Recipe\Category;
use Recipes\Security\Security;

$auth = getAuthManager();
$id = sanitizeInt($_GET['id'] ?? 0);
if (empty($id)) {
  header("Location: index.php");
  exit;
}

// Load recipe
$res = Database::query("SELECT rec_id, rec_title, rec_last_updated, rec_source, rec_url, rec_date_added, rec_favorite, rec_category FROM rec_recipe WHERE rec_id = ?", [$id]);
if ( ! $res ) {
  die_miserable_death ( "Db error: " . Database::error() );
}
$row = Database::fetchRow($res);
if (!$row) {
  header("Location: index.php");
  exit;
}
$recTitle = $row[1];
$lastUpdated = $row[2];
$source = $row[3];
$recUrl = $row[4];
$dateAdded = $row[5];
$isFavorite = $row[6] ? true : false;
$recCategory = $row[7] ?? '';
Database::freeResult($res);

// Load photos
$photos = [];
$primaryPhoto = null;
$res = Database::query(
    "SELECT photo_id, original_name, is_primary, created_at FROM rec_photo WHERE rec_id = ? ORDER BY is_primary DESC, created_at ASC",
    [$id]
);
if ($res) {
    while ($row = Database::fetchRow($res)) {
        $photo = [
            'id' => $row[0],
            'original_name' => $row[1],
            'is_primary' => $row[2],
            'created_at' => $row[3],
        ];
        $photos[] = $photo;
        if ($row[2] == 1) {
            $primaryPhoto = $photo;
        }
    }
    Database::freeResult($res);
}

// Load notes
$notes = [];
$res = Database::query(
    "SELECT note_id, note_text, created_at FROM rec_note WHERE rec_id = ? ORDER BY created_at DESC",
    [$id]
);
if ($res) {
    while ($row = Database::fetchRow($res)) {
        $notes[] = [
            'id' => $row[0],
            'text' => $row[1],
            'created_at' => $row[2],
        ];
    }
    Database::freeResult($res);
}

// Load ingredients
$ingredients = [];
$res = Database::query("SELECT rec_ingr_num, rec_quantity, rec_quantity_type, rec_name, rec_prep FROM rec_ingr WHERE rec_id = ? ORDER BY rec_ingr_num", [$id]);
if ($res) {
    while ($row = Database::fetchRow($res)) {
        $ingredients[] = [
            'qty'  => $row[1] ?? '',
            'unit' => $row[2] ?? '',
            'name' => $row[3] ?? '',
            'prep' => $row[4] ?? '',
        ];
    }
    Database::freeResult($res);
}

// Load instructions
$instructionsText = '';
$res = Database::query("SELECT rec_instructions FROM rec_instructions WHERE rec_id = ?", [$id]);
if ($res && ($row = Database::fetchRow($res))) {
    $instructionsText = $row[0];
}
if ($res) Database::freeResult($res);

$pagetitle = 'Recipe: ' . htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8');

// Build JSON-LD
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'Recipe',
    'name'     => $recTitle,
];

if (!empty($source)) {
    $jsonLd['author'] = ['@type' => 'Person', 'name' => $source];
}
if (Security::isHttpUrl((string)$recUrl)) {
    $jsonLd['url'] = $recUrl;
}
if (!empty($dateAdded)) {
    $jsonLd['datePublished'] = date('Y-m-d', strtotime($dateAdded));
}
if (!empty($lastUpdated)) {
    $jsonLd['dateModified'] = date('Y-m-d', strtotime($lastUpdated));
}
if ($primaryPhoto) {
    // Use absolute URL for the image
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $jsonLd['image'] = $scheme . '://' . $host . $basePath . '/image.php?id=' . (int)$primaryPhoto['id'];
}

if (!empty($ingredients)) {
    $jsonLd['recipeIngredient'] = [];
    foreach ($ingredients as $ingr) {
        $text = trim($ingr['qty'] . ' ' . $ingr['unit'] . ' ' . $ingr['name']);
        if (!empty($ingr['prep'])) {
            $text .= ', ' . $ingr['prep'];
        }
        $jsonLd['recipeIngredient'][] = trim($text);
    }
}

if (!empty($instructionsText)) {
    $steps = preg_split('/\n{2,}/', trim($instructionsText));
    $jsonLd['recipeInstructions'] = [];
    foreach ($steps as $step) {
        $step = trim($step);
        if ($step !== '') {
            $jsonLd['recipeInstructions'][] = [
                '@type' => 'HowToStep',
                'text'  => $step,
            ];
        }
    }
}

?>
<html>
<head>
<title><?php echo $pagetitle;?></title>
<?php include "includes/styles.php"; ?>
<?php include "includes/js.php"; ?>
<?php require_once "../style.css"; ?>
<link rel="stylesheet" type="text/css" href="./styles-print.css" media="print" />
<script type="application/ld+json"><?php echo Security::jsonForScriptTag($jsonLd); ?></script>
</head>
<body style="margin: 0">
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="doNotPrint">
<?php require_once "../header.php"; ?>
</div>

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
        <?php if ($auth->can('edit')) { ?>
        <li class="nav-item">
          <a class="nav-link" href="edit.php"><?php echo t('navigation.add_recipe'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="import.php"><?php echo t('recipe.import'); ?></a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="paste.php"><?php echo t('recipe.paste'); ?></a>
        </li>
        <?php } ?>
      </ul>
      <ul class="navbar-nav ms-auto">
        <?php if ($auth->getMode() === 'pin') { ?>
          <?php if ($auth->can('edit')) { ?>
            <li class="nav-item"><a class="nav-link" href="auth_handler.php?action=logout" title="Lock">🔓</a></li>
          <?php } else { ?>
            <li class="nav-item"><a class="nav-link" href="login.php" title="Unlock">🔒</a></li>
          <?php } ?>
        <?php } elseif ($auth->getMode() === 'user') { ?>
          <?php if ($auth->can('edit')) { ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo htmlspecialchars($auth->getCurrentUser()['username']); ?>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><a class="dropdown-menu" href="auth_handler.php?action=logout">Logout</a></li>
              </ul>
            </li>
          <?php } else { ?>
            <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <?php } ?>
        <?php } ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4">

<?php if (!empty($_GET['error'])) { ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php } ?>

<div class="card">
<div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
<h1 class="h4 mb-0"><?php echo htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
<div class="d-flex gap-2 doNotPrint">
<button type="button" onclick="window.print()" class="btn btn-sm btn-outline-light" title="Print recipe">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
    <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
    <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 10a1 1 0 0 1-1-1v-4h8v4a1 1 0 0 1-1 1z"/>
  </svg>
</button>
<?php if ($auth->can('edit', (int)$id)) { ?>
<button type="button" id="favBtn" class="btn btn-sm <?php echo $isFavorite ? 'btn-warning' : 'btn-outline-light'; ?>"
        data-rec-id="<?php echo (int)$id; ?>"
        data-csrf="<?php echo generateCsrfToken(); ?>"
        title="<?php echo $isFavorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
    <?php if ($isFavorite) { ?>
    <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
    <?php } else { ?>
    <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767l-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z"/>
    <?php } ?>
  </svg>
</button>
<?php } ?>
</div>
</div>
<div class="card-body">

<?php if ($primaryPhoto) { ?>
<div class="text-center mb-4">
  <img src="image.php?id=<?php echo (int)$primaryPhoto['id']; ?>"
       alt="<?php echo htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8'); ?>"
       class="img-fluid rounded photo-zoom" style="max-height: 400px; object-fit: contain; cursor: pointer;"
       data-photo-src="image.php?id=<?php echo (int)$primaryPhoto['id']; ?>">
</div>
<?php } ?>

<?php if (!empty($source)) { ?>
<p class="text-muted"><strong><?php echo t('recipe.source'); ?>:</strong> <?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<?php if (!empty($recCategory)) { 
  $cat = Category::tryFrom($recCategory);
  $catLabel = $cat ? $cat->label() : $recCategory;
?>
<p class="text-muted"><strong><?php echo t('recipe.category'); ?>:</strong> <?php echo htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>

<?php if (Security::isHttpUrl((string)$recUrl)) { ?>
<p class="text-muted"><strong>URL:</strong> <a href="<?php echo htmlspecialchars($recUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($recUrl, ENT_QUOTES, 'UTF-8'); ?></a></p>
<?php } ?>

<?php if (!empty($dateAdded) || !empty($lastUpdated)) { ?>
<p class="text-muted small">
<?php if (!empty($dateAdded)) { ?>
  Added: <?php echo date('M j, Y', strtotime($dateAdded)); ?>
<?php } ?>
<?php if (!empty($dateAdded) && !empty($lastUpdated)) { ?> | <?php } ?>
<?php if (!empty($lastUpdated)) { ?>
  Updated: <?php echo date('M j, Y g:ia', strtotime($lastUpdated)); ?>
<?php } ?>
</p>
<?php } ?>

<div class="d-flex align-items-center mb-2 flex-wrap gap-2">
  <h5 class="mb-0 me-2"><?php echo t('recipe.ingredients'); ?></h5>
  <div class="btn-group btn-group-sm doNotPrint" role="group" aria-label="Scale recipe">
    <button type="button" class="btn btn-outline-secondary scale-btn" data-scale="0.5">&frac12;x</button>
    <button type="button" class="btn btn-outline-secondary scale-btn" data-scale="0.6667">&frac23;x</button>
    <button type="button" class="btn btn-secondary scale-btn active" data-scale="1">1x</button>
    <button type="button" class="btn btn-outline-secondary scale-btn" data-scale="2">2x</button>
    <button type="button" class="btn btn-outline-secondary scale-btn" data-scale="3">3x</button>
  </div>
</div>
<ul class="list-group list-group-flush mb-4" id="ingredientList">
<?php
foreach ($ingredients as $ingr) {
  $qty = $ingr['qty'];
  $unit = htmlspecialchars($ingr['unit'], ENT_QUOTES, 'UTF-8');
  $name = htmlspecialchars($ingr['name'], ENT_QUOTES, 'UTF-8');
  $prep = htmlspecialchars($ingr['prep'], ENT_QUOTES, 'UTF-8');

  if ($qty !== '' && $qty !== null) {
    $displayQty = htmlspecialchars($qty, ENT_QUOTES, 'UTF-8');
    $rest = trim("$unit $name");
    if (!empty($prep)) $rest .= ", $prep";
    echo "<li class=\"list-group-item\" data-orig-qty=\"" . htmlspecialchars($qty, ENT_QUOTES, 'UTF-8') . "\">" .
         "<span class=\"ingr-qty\">" . $displayQty . "</span> " . $rest . "</li>\n";
  } else {
    $ingrText = trim($name);
    if (!empty($prep)) $ingrText .= ", $prep";
    echo "<li class=\"list-group-item\" data-orig-text=\"" . htmlspecialchars($ingrText, ENT_QUOTES, 'UTF-8') . "\">" . $ingrText . "</li>\n";
  }
}
?>
</ul>

<?php if (!empty($instructionsText)) { ?>
<h5><?php echo t('recipe.instructions'); ?></h5>
<div class="card card-body bg-light mb-4"><?php echo nl2br(htmlspecialchars($instructionsText, ENT_QUOTES, 'UTF-8')); ?></div>
<?php } ?>

<!-- Photo Gallery Section -->
<?php if (!empty($photos)) { ?>
<h5 class="mt-4 doNotPrint"><?php echo t('recipe.photos'); ?></h5>
<div class="row g-3 mb-4 doNotPrint">
  <?php foreach ($photos as $photo) { ?>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="card">
      <img src="image.php?id=<?php echo (int)$photo['id']; ?>"
           class="card-img-top photo-zoom" alt="Recipe photo"
           style="height: 150px; object-fit: cover; cursor: pointer;"
           data-photo-src="image.php?id=<?php echo (int)$photo['id']; ?>">
      <div class="card-body p-2 text-center">
        <small class="text-muted d-block">
          <?php echo date('M j, Y g:ia', strtotime($photo['created_at'])); ?>
        </small>
        <div class="doNotPrint mt-1">
          <?php if ($auth->can('edit', (int)$id)) { ?>
            <?php if ($photo['is_primary'] == 1) { ?>
              <span class="badge bg-success">Primary</span>
            <?php } else { ?>
              <form method="post" action="photo_handler.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="set_primary">
                <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
                <button type="submit" class="btn btn-outline-success btn-sm" title="Set as primary">Primary</button>
              </form>
            <?php } ?>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#deletePhotoModal<?php echo (int)$photo['id']; ?>"
                    title="<?php echo t('navigation.delete'); ?>"><?php echo t('navigation.delete'); ?></button>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Photo Confirmation Modal -->
  <div class="modal fade" id="deletePhotoModal<?php echo (int)$photo['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this photo? This cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form method="post" action="photo_handler.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="photo_id" value="<?php echo (int)$photo['id']; ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>
</div>
<?php } ?>

<!-- Upload Photo Form -->
<?php if ($auth->can('edit', (int)$id)) { ?>
<div class="doNotPrint mb-4">
  <h6>Add Photo</h6>
  <form method="post" action="photo_handler.php" enctype="multipart/form-data" class="row g-2 align-items-end">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="upload">
    <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
    <div class="col-auto">
      <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" required>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Upload</button>
    </div>
    <div class="col-12">
      <small class="text-muted">JPG, PNG, GIF, or WebP. Max 5MB.</small>
    </div>
  </form>
</div>
<?php } ?>

<!-- Notes Section -->
<h5 class="mt-4"><?php echo t('recipe.notes'); ?></h5>
<?php if (!empty($notes)) { ?>
<div class="mb-3">
  <?php foreach ($notes as $note) { ?>
  <div class="card mb-2" id="noteCard<?php echo (int)$note['id']; ?>">
    <div class="card-body py-2 px-3">
      <!-- Display mode -->
      <div class="note-display" id="noteDisplay<?php echo (int)$note['id']; ?>">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <?php echo nl2br(htmlspecialchars($note['text'], ENT_QUOTES, 'UTF-8')); ?>
          </div>
          <?php if ($auth->can('edit', (int)$id)) { ?>
          <div class="doNotPrint ms-2 d-flex gap-1">
            <button type="button" class="btn btn-outline-secondary btn-sm note-edit-btn"
                    data-note-id="<?php echo (int)$note['id']; ?>"><?php echo t('navigation.edit'); ?></button>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteNoteModal<?php echo (int)$note['id']; ?>"><?php echo t('navigation.delete'); ?></button>
          </div>
          <?php } ?>
        </div>
        <small class="text-muted">
          <?php echo date('M j, Y g:ia', strtotime($note['created_at'])); ?>
        </small>
      </div>
      <!-- Edit mode (hidden by default) -->
      <?php if ($auth->can('edit', (int)$id)) { ?>
      <div class="note-edit d-none" id="noteEdit<?php echo (int)$note['id']; ?>">
        <form method="post" action="note_handler.php">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
          <input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
          <div class="mb-2">
            <textarea class="form-control" name="note_text" rows="3" required><?php echo htmlspecialchars($note['text'], ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?php echo t('navigation.save'); ?></button>
            <button type="button" class="btn btn-secondary btn-sm note-cancel-btn"
                    data-note-id="<?php echo (int)$note['id']; ?>"><?php echo t('navigation.cancel'); ?></button>
          </div>
        </form>
      </div>
      <?php } ?>
    </div>
  </div>

  <!-- Delete Note Confirmation Modal -->
  <div class="modal fade" id="deleteNoteModal<?php echo (int)$note['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Delete Note</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to delete this note? This cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <form method="post" action="note_handler.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
            <input type="hidden" name="note_id" value="<?php echo (int)$note['id']; ?>">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>
</div>
<?php } else { ?>
<p class="text-muted mb-3">No notes yet.</p>
<?php } ?>

<!-- Add Note Form -->
<?php if ($auth->can('edit', (int)$id)) { ?>
<div class="doNotPrint mb-4">
  <form method="post" action="note_handler.php">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="rec_id" value="<?php echo (int)$id; ?>">
    <div class="mb-2">
      <textarea class="form-control" name="note_text" rows="2" placeholder="Add a note..." required></textarea>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
  </form>
</div>
<?php } ?>

<?php if ($auth->can('edit', (int)$id)) { ?>
<div class="doNotPrint mt-3">
  <a href="edit.php?id=<?php echo (int)$id; ?>" class="btn btn-primary"><?php echo t('navigation.edit'); ?></a>
  <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><?php echo t('navigation.delete'); ?></button>
</div>
<?php } ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete "<strong><?php echo htmlspecialchars($recTitle, ENT_QUOTES, 'UTF-8'); ?></strong>"? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="post" action="delete_handler.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
          <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
          <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Fraction / number utilities ---
    const NICE_FRACTIONS = {
        '0.125': '\u215B',   // ⅛
        '0.25':  '\u00BC',   // ¼
        '0.333': '\u2153',   // ⅓
        '0.375': '\u215C',   // ⅜
        '0.5':   '\u00BD',   // ½
        '0.625': '\u215D',   // ⅝
        '0.667': '\u2154',   // ⅔
        '0.75':  '\u00BE',   // ¾
        '0.875': '\u215E'    // ⅞
    };

    function parseFraction(s) {
        s = s.trim();
        if (s === '') return null;
        // Unicode fraction characters
        const unicodeFracs = {'\u00BC':0.25, '\u00BD':0.5, '\u00BE':0.75,
            '\u2153':1/3, '\u2154':2/3, '\u215B':0.125, '\u215C':0.375,
            '\u215D':0.625, '\u215E':0.875};
        for (const uf in unicodeFracs) {
            if (s.indexOf(uf) !== -1) {
                const rest = s.replace(uf, '').trim();
                let whole = rest ? parseFloat(rest) : 0;
                if (isNaN(whole)) whole = 0;
                return whole + unicodeFracs[uf];
            }
        }
        // "1 1/2" or "1/2"
        const mixed = s.match(/^(\d+)\s+(\d+)\/(\d+)$/);
        if (mixed) return parseInt(mixed[1]) + parseInt(mixed[2]) / parseInt(mixed[3]);
        const frac = s.match(/^(\d+)\/(\d+)$/);
        if (frac) return parseInt(frac[1]) / parseInt(frac[2]);
        const num = parseFloat(s);
        return isNaN(num) ? null : num;
    }

    function formatNumber(n) {
        if (n === 0) return '0';
        const whole = Math.floor(n);
        const frac = n - whole;
        // Round fractional part to 3 decimal places for matching
        const fracRound = (Math.round(frac * 1000) / 1000).toFixed(3);
        let nice = NICE_FRACTIONS[fracRound];
        if (nice) {
            return whole > 0 ? whole + nice : nice;
        }
        // Try rounding to common fractions
        if (frac > 0.01) {
            // Check nearby values
            const candidates = [0.125, 0.25, 0.333, 0.375, 0.5, 0.625, 0.667, 0.75, 0.875];
            for (let i = 0; i < candidates.length; i++) {
                if (Math.abs(frac - candidates[i]) < 0.03) {
                    nice = NICE_FRACTIONS[candidates[i].toFixed(3)];
                    if (nice) return whole > 0 ? whole + nice : nice;
                }
            }
        }
        // Clean decimal: avoid things like 1.5000000001
        if (whole === n) return whole.toString();
        const rounded = Math.round(n * 100) / 100;
        // Remove trailing zeros after decimal
        return rounded.toString().replace(/\.?0+$/, '');
    }

    // Regex to find leading numbers in unstructured ingredient text
    // Matches: "3/4", "1 1/2", "12.5", "2", or unicode fractions at the start
    const NUM_RE = /^((?:\d+\s+)?\d+\/\d+|\d+\.?\d*|[\u00BC\u00BD\u00BE\u2153\u2154\u215B\u215C\u215D\u215E](?:\s*\d+)?|\d+[\u00BC\u00BD\u00BE\u2153\u2154\u215B\u215C\u215D\u215E])/;

    function scaleText(origText, scale) {
        const m = origText.match(NUM_RE);
        if (!m) return origText;
        const matchStr = m[1];
        const parsed = parseFraction(matchStr);
        if (parsed === null) return origText;
        const scaled = parsed * scale;
        return formatNumber(scaled) + origText.substring(matchStr.length);
    }

    // --- Note inline edit toggle ---
    document.querySelectorAll('.note-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var noteId = this.dataset.noteId;
            document.getElementById('noteDisplay' + noteId).classList.add('d-none');
            document.getElementById('noteEdit' + noteId).classList.remove('d-none');
        });
    });
    document.querySelectorAll('.note-cancel-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var noteId = this.dataset.noteId;
            document.getElementById('noteEdit' + noteId).classList.add('d-none');
            document.getElementById('noteDisplay' + noteId).classList.remove('d-none');
        });
    });

    // --- Scale button handler ---
    document.querySelectorAll('.scale-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const scale = parseFloat(this.dataset.scale);
            document.querySelectorAll('.scale-btn').forEach(function(b) {
                b.classList.remove('btn-secondary', 'active');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-secondary', 'active');

            document.querySelectorAll('#ingredientList li').forEach(function(li) {
                const origQty = li.dataset.origQty;
                const origText = li.dataset.origText;

                if (origQty !== undefined && origQty !== '') {
                    // Structured ingredient: scale the qty span
                    const parsed = parseFloat(origQty);
                    if (!isNaN(parsed)) {
                        const qtySpan = li.querySelector('.ingr-qty');
                        if (qtySpan) qtySpan.textContent = formatNumber(parsed * scale);
                    }
                } else if (origText !== undefined) {
                    // Unstructured: parse and scale leading number in full text
                    li.innerHTML = scaleText(origText, scale);
                }
            });
        });
    });
});
</script>

<!-- Photo Lightbox Modal -->
<div class="modal fade" id="photoLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-transparent border-0 shadow-none">
      <div class="modal-body p-0 text-center">
        <img id="lightboxImg" src="" alt="Photo" class="img-fluid" style="max-height: 90vh; border-radius: 8px;">
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', function(e) {
    const photoZoom = e.target.closest('.photo-zoom');
    if (photoZoom) {
        document.getElementById('lightboxImg').src = photoZoom.dataset.photoSrc;
        new bootstrap.Modal(document.getElementById('photoLightbox')).show();
    }
});

const favBtn = document.getElementById('favBtn');
if (favBtn) {
    favBtn.addEventListener('click', function() {
        const btn = this;
        const formData = new FormData();
        formData.append('csrf_token', btn.dataset.csrf);
        formData.append('rec_id', btn.dataset.recId);

        fetch('favorite_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(function(resp) {
            if (resp.error) return;
            const filled = '<path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>';
            const outline = '<path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767l-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z"/>';
            if (resp.favorite) {
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-warning');
                btn.title = 'Remove from favorites';
                btn.querySelector('svg').innerHTML = filled;
            } else {
                btn.classList.remove('btn-warning');
                btn.classList.add('btn-outline-light');
                btn.title = 'Add to favorites';
                btn.querySelector('svg').innerHTML = outline;
            }
        });
    });
}
</script>

<?php include "trailer.php"; ?>
