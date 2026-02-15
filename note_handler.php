<?php

include "rec_includes.php";

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  die_miserable_death("Invalid CSRF token.");
}

$action = $_POST['action'] ?? '';
$recId = sanitizeInt($_POST['rec_id'] ?? '');

if (empty($recId)) {
  header("Location: index.php");
  exit;
}

// Verify recipe exists
$res = BookLogDB::query("SELECT rec_id FROM rec_recipe WHERE rec_id = ?", [$recId]);
if (!$res || !BookLogDB::fetchRow($res)) {
  header("Location: index.php");
  exit;
}
BookLogDB::freeResult($res);

$redirectUrl = "view.php?id=" . (int)$recId;

if ($action === 'add') {

  $noteText = trim($_POST['note_text'] ?? '');
  if (empty($noteText)) {
    header("Location: $redirectUrl&error=" . urlencode("Note text cannot be empty."));
    exit;
  }

  BookLogDB::query(
    "INSERT INTO rec_note (rec_id, note_text) VALUES (?, ?)",
    [$recId, $noteText]
  );

  BookLogDB::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} elseif ($action === 'delete') {

  $noteId = sanitizeInt($_POST['note_id'] ?? '');
  if (empty($noteId)) {
    header("Location: $redirectUrl");
    exit;
  }

  // Delete with rec_id guard to prevent cross-recipe deletion
  BookLogDB::query("DELETE FROM rec_note WHERE note_id = ? AND rec_id = ?", [$noteId, $recId]);

  BookLogDB::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} else {
  header("Location: $redirectUrl");
  exit;
}

?>
