<?php

include "rec_includes.php";

use function Recipes\I18n\t;
use function Recipes\Auth\getAuthManager;
use Recipes\Database\Database;
use Recipes\Recipe\Category;

$auth = getAuthManager();
$pagetitle = $title . ' ' . t('navigation.home');

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
          <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php"><?php echo t('navigation.home'); ?></a>
        </li>
        <?php if ($auth->can('edit')) { ?>
        <li class="nav-item">
          <a class="nav-link <?php echo $current_page == 'edit.php' ? 'active' : ''; ?>" href="edit.php"><?php echo t('navigation.add_recipe'); ?></a>
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

<div class="card">
<div class="card-header bg-primary text-white">
<h1 class="h4 mb-0"><?php echo t('app.name'); ?></h1>
</div>
<div class="card-body">

<div class="row mb-3 g-2 align-items-center">
  <div class="col">
    <input type="text" id="searchInput" class="form-control" placeholder="Filter recipes...">
  </div>
  <div class="col-auto">
    <select id="categoryFilter" class="form-select">
      <option value=""><?php echo t('recipe.category'); ?>: All</option>
      <?php foreach (Category::cases() as $cat) { ?>
        <option value="<?php echo $cat->value; ?>"><?php echo $cat->label(); ?></option>
      <?php } ?>
    </select>
  </div>
  <div class="col-auto">
    <select id="sortSelect" class="form-select">
      <option value="fav-updated" selected>Favorites First</option>
      <option value="updated-desc">Recently Updated</option>
      <option value="updated-asc">Oldest Updated</option>
      <option value="title-asc">Title A-Z</option>
      <option value="title-desc">Title Z-A</option>
      <option value="added-desc">Recently Added</option>
      <option value="added-asc">Oldest Added</option>
    </select>
  </div>
  <div class="col-auto">
    <div class="btn-group" role="group" aria-label="View mode">
      <button type="button" class="btn btn-outline-secondary active" id="btnGridView" title="Grid view">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
        </svg>
      </button>
      <button type="button" class="btn btn-outline-secondary" id="btnTableView" title="Table view">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
        </svg>
      </button>
    </div>
  </div>
</div>

<?php

$res = Database::query(
  "SELECT r.rec_id, r.rec_title, r.rec_last_updated, r.rec_source, p.photo_id, r.rec_date_added, r.rec_favorite, r.rec_category " .
  "FROM rec_recipe r " .
  "LEFT JOIN rec_photo p ON r.rec_id = p.rec_id AND p.is_primary = 1 " .
  "ORDER BY r.rec_favorite DESC, r.rec_last_updated DESC"
);
if ( ! $res ) {
  echo "Database error: " . Database::error();
  exit;
}

?>

<p id="recipeCount" class="text-muted mb-2"></p>

<!-- Grid View -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="recipeGrid">

<?php

$recipes = [];
while ( $row = Database::fetchRow($res) ) {
  $recipes[] = $row;
}
Database::freeResult($res);

foreach ($recipes as $row) {
  $recId = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
  $recTitle = htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8');
  $recSource = htmlspecialchars($row[3] ?? '', ENT_QUOTES, 'UTF-8');
  $sortUpdated = $row[2] ?? '0000-00-00';
  $sortAdded = $row[5] ?? '0000-00-00';
  $recDate = '';
  if (!empty($row[2])) {
    $recDate = date('M j, Y', strtotime($row[2]));
  }
  $photoId = $row[4] ?? null;
  $isFav = $row[6] ? 1 : 0;
  $recCategory = $row[7] ?? '';
?>
  <div class="col recipe-card"
       data-title="<?php echo $recTitle; ?>"
       data-updated="<?php echo htmlspecialchars($sortUpdated, ENT_QUOTES, 'UTF-8'); ?>"
       data-added="<?php echo htmlspecialchars($sortAdded, ENT_QUOTES, 'UTF-8'); ?>"
       data-fav="<?php echo $isFav; ?>"
       data-id="<?php echo $recId; ?>"
       data-source="<?php echo $recSource; ?>"
       data-photo="<?php echo $photoId ? (int)$photoId : ''; ?>"
       data-category="<?php echo htmlspecialchars($recCategory, ENT_QUOTES, 'UTF-8'); ?>"
       data-date="<?php echo $recDate; ?>">
    <div class="card h-100">
      <?php if ($photoId) { ?>
        <a href="view.php?id=<?php echo $recId; ?>">
          <img src="image.php?id=<?php echo (int)$photoId; ?>"
               class="card-img-top" alt="<?php echo $recTitle; ?>"
               style="height: 200px; object-fit: cover;">
        </a>
      <?php } else { ?>
        <a href="view.php?id=<?php echo $recId; ?>" class="text-decoration-none">
          <div class="card-img-top d-flex align-items-center justify-content-center bg-light"
               style="height: 200px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#ccc" viewBox="0 0 16 16">
              <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
              <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
            </svg>
          </div>
        </a>
      <?php } ?>
      <div class="card-body">
        <h5 class="card-title">
          <?php if ($isFav) { ?><span class="text-warning" title="Favorite">&#9733; </span><?php } ?>
          <a href="view.php?id=<?php echo $recId; ?>" class="text-decoration-none"><?php echo $recTitle; ?></a>
        </h5>
        <?php if (!empty($recSource)) { ?>
          <p class="card-text text-muted small mb-0"><?php echo $recSource; ?></p>
        <?php } ?>
      </div>
      <?php if (!empty($recDate)) { ?>
        <div class="card-footer text-muted small"><?php echo $recDate; ?></div>
      <?php } ?>
    </div>
  </div>
<?php
}
?>

</div>

<!-- Table View (hidden by default) -->
<table class="table table-hover" id="recipeTable" style="display: none;">
<thead>
<tr>
  <th style="width: 50px;"></th>
  <th style="width: 30px;"></th>
  <th>Title</th>
  <th class="d-none d-md-table-cell">Source</th>
  <th class="d-none d-md-table-cell" style="width: 120px;">Updated</th>
</tr>
</thead>
<tbody>
<?php foreach ($recipes as $row) {
  $recId = htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8');
  $recTitle = htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8');
  $recSource = htmlspecialchars($row[3] ?? '', ENT_QUOTES, 'UTF-8');
  $recDate = '';
  if (!empty($row[2])) {
    $recDate = date('M j, Y', strtotime($row[2]));
  }
  $photoId = $row[4] ?? null;
  $isFav = $row[6] ? 1 : 0;
  $recCategory = $row[7] ?? '';
?>
<tr class="recipe-row"
    data-title="<?php echo $recTitle; ?>"
    data-updated="<?php echo htmlspecialchars($row[2] ?? '0000-00-00', ENT_QUOTES, 'UTF-8'); ?>"
    data-added="<?php echo htmlspecialchars($row[5] ?? '0000-00-00', ENT_QUOTES, 'UTF-8'); ?>"
    data-fav="<?php echo $isFav; ?>"
    data-category="<?php echo htmlspecialchars($recCategory, ENT_QUOTES, 'UTF-8'); ?>">
  <td class="align-middle p-1">
    <?php if ($photoId) { ?>
      <a href="view.php?id=<?php echo $recId; ?>">
        <img src="image.php?id=<?php echo (int)$photoId; ?>" alt="" style="width: 45px; height: 35px; object-fit: cover; border-radius: 4px;">
      </a>
    <?php } ?>
  </td>
  <td class="align-middle p-1 text-warning"><?php echo $isFav ? '&#9733;' : ''; ?></td>
  <td class="align-middle text-truncate" style="max-width: 300px;">
    <a href="view.php?id=<?php echo $recId; ?>" class="text-decoration-none"><?php echo $recTitle; ?></a>
  </td>
  <td class="align-middle text-muted small d-none d-md-table-cell"><?php echo $recSource; ?></td>
  <td class="align-middle text-muted small d-none d-md-table-cell"><?php echo $recDate; ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('recipeGrid');
    const table = document.getElementById('recipeTable');
    let viewMode = 'grid';

    function updateRecipeCount() {
        const selector = viewMode === 'grid' ? '.recipe-card' : '.recipe-row';
        const totalSelector = viewMode === 'grid' ? '.recipe-card' : '.recipe-row';
        const allItems = document.querySelectorAll(totalSelector);
        const visibleItems = document.querySelectorAll(selector + ':not([style*="display: none"])');
        const visible = visibleItems.length;
        const total = allItems.length;
        const countEl = document.getElementById('recipeCount');
        if (visible === total) {
            countEl.textContent = total + ' recipes';
        } else {
            countEl.textContent = 'Showing ' + visible + ' of ' + total + ' recipes';
        }
    }

    function applyFilter() {
        const textValue = document.getElementById('searchInput').value.toLowerCase();
        const catValue = document.getElementById('categoryFilter').value;

        if (viewMode === 'grid') {
            grid.querySelectorAll('.recipe-card').forEach(function(card) {
                const matchesText = card.textContent.toLowerCase().indexOf(textValue) > -1;
                const matchesCat = catValue === '' || card.dataset.category === catValue;
                card.style.display = (matchesText && matchesCat) ? '' : 'none';
            });
        } else {
            table.querySelectorAll('.recipe-row').forEach(function(row) {
                const matchesText = row.textContent.toLowerCase().indexOf(textValue) > -1;
                const matchesCat = catValue === '' || row.dataset.category === catValue;
                row.style.display = (matchesText && matchesCat) ? '' : 'none';
            });
        }
        updateRecipeCount();
    }

    function applySort() {
        const sortVal = document.getElementById('sortSelect').value;

        // Sort grid cards
        const cards = Array.from(grid.querySelectorAll('.recipe-card'));
        cards.sort(makeSorter(sortVal));
        cards.forEach(function(card) {
            grid.appendChild(card);
        });

        // Sort table rows
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.recipe-row'));
        rows.sort(makeSorter(sortVal));
        rows.forEach(function(row) {
            tbody.appendChild(row);
        });

        updateRecipeCount();
    }

    function makeSorter(sortVal) {
        return function(a, b) {
            const favA = parseInt(a.dataset.fav) || 0;
            const favB = parseInt(b.dataset.fav) || 0;

            if (sortVal === 'fav-updated') {
                if (favA !== favB) return favB - favA;
                return (b.dataset.updated || '').localeCompare(a.dataset.updated || '');
            }

            switch (sortVal) {
                case 'title-asc':
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                case 'title-desc':
                    return (b.dataset.title || '').localeCompare(a.dataset.title || '');
                case 'updated-desc':
                    return (b.dataset.updated || '').localeCompare(a.dataset.updated || '');
                case 'updated-asc':
                    return (a.dataset.updated || '').localeCompare(b.dataset.updated || '');
                case 'added-desc':
                    return (b.dataset.added || '').localeCompare(a.dataset.added || '');
                case 'added-asc':
                    return (a.dataset.added || '').localeCompare(b.dataset.added || '');
                default:
                    return 0;
            }
        };
    }

    // View toggle
    document.getElementById('btnGridView').addEventListener('click', function() {
        if (viewMode === 'grid') return;
        viewMode = 'grid';
        table.style.display = 'none';
        grid.style.display = '';
        document.getElementById('btnGridView').classList.add('active');
        document.getElementById('btnTableView').classList.remove('active');
        applyFilter();
    });

    document.getElementById('btnTableView').addEventListener('click', function() {
        if (viewMode === 'table') return;
        viewMode = 'table';
        grid.style.display = 'none';
        table.style.display = '';
        document.getElementById('btnTableView').classList.add('active');
        document.getElementById('btnGridView').classList.remove('active');
        applyFilter();
    });

    // Restore saved sort order from localStorage
    var savedSort = localStorage.getItem('recipeSortOrder');
    if (savedSort) {
        document.getElementById('sortSelect').value = savedSort;
        applySort();
    }

    document.getElementById('searchInput').addEventListener('keyup', applyFilter);
    document.getElementById('categoryFilter').addEventListener('change', applyFilter);
    document.getElementById('sortSelect').addEventListener('change', function() {
        localStorage.setItem('recipeSortOrder', this.value);
        applySort();
        applyFilter();
    });

    updateRecipeCount();
});
</script>

<?php include "trailer.php"; ?>
