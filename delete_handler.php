<?php

include "rec_includes.php";

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  die_miserable_death("Invalid CSRF token.");
}

$id = sanitizeInt($_POST['id'] ?? '');
if (empty($id)) {
  header("Location: index.php");
  exit;
}

BookLogDB::beginTransaction();

try {
  // Delete children first
  BookLogDB::query("DELETE FROM rec_photo WHERE rec_id = ?", [$id]);
  BookLogDB::query("DELETE FROM rec_note WHERE rec_id = ?", [$id]);
  BookLogDB::query("DELETE FROM rec_instructions WHERE rec_id = ?", [$id]);
  BookLogDB::query("DELETE FROM rec_ingr WHERE rec_id = ?", [$id]);
  BookLogDB::query("DELETE FROM rec_recipe WHERE rec_id = ?", [$id]);

  BookLogDB::commit();
} catch (Exception $e) {
  BookLogDB::rollback();
  die_miserable_death("Error deleting recipe: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header("Location: index.php");
exit;

?>
