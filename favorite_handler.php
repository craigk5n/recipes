<?php

include "rec_includes.php";

header('Content-Type: application/json');

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  echo json_encode(['error' => 'Invalid CSRF token.']);
  exit;
}

$recId = sanitizeInt($_POST['rec_id'] ?? '');
if (empty($recId)) {
  echo json_encode(['error' => 'Missing recipe ID.']);
  exit;
}

// Toggle favorite
$res = BookLogDB::query("SELECT rec_favorite FROM rec_recipe WHERE rec_id = ?", [$recId]);
if (!$res || !($row = BookLogDB::fetchRow($res))) {
  echo json_encode(['error' => 'Recipe not found.']);
  exit;
}
BookLogDB::freeResult($res);

$newVal = $row[0] ? 0 : 1;
BookLogDB::query("UPDATE rec_recipe SET rec_favorite = ? WHERE rec_id = ?", [$newVal, $recId]);

echo json_encode(['favorite' => $newVal]);
exit;

?>
