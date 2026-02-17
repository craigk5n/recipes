<?php

include "rec_includes.php";

use Recipes\Database\Database;
use function Recipes\Auth\getAuthManager;

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  echo json_encode(['error' => 'Invalid CSRF token.']);
  exit;
}

$recId = sanitizeInt($_POST['rec_id'] ?? '');
if (empty($recId)) {
  echo json_encode(['error' => 'Missing recipe ID.']);
  exit;
}

if (!getAuthManager()->can('edit', (int)$recId)) {
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

// Toggle favorite
$res = Database::query("SELECT rec_favorite FROM rec_recipe WHERE rec_id = ?", [$recId]);
if (!$res || !($row = Database::fetchRow($res))) {
  echo json_encode(['error' => 'Recipe not found.']);
  exit;
}
Database::freeResult($res);

$newVal = $row[0] ? 0 : 1;
Database::query("UPDATE rec_recipe SET rec_favorite = ? WHERE rec_id = ?", [$newVal, $recId]);

echo json_encode(['favorite' => $newVal]);
exit;

?>
