<?php

include "rec_includes.php";

use Recipes\Database\Database;
use function Recipes\Auth\getAuthManager;

$id = sanitizeInt($_POST['id'] ?? '');
if (empty($id)) {
  header("Location: index.php");
  exit;
}

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  die_miserable_death("Invalid CSRF token.");
}

if (!getAuthManager()->can('delete', (int)$id)) {
  die_miserable_death("Unauthorized: You do not own this recipe.");
}

Database::beginTransaction();

try {
  // Delete children first
  Database::query("DELETE FROM rec_photo WHERE rec_id = ?", [$id]);
  Database::query("DELETE FROM rec_note WHERE rec_id = ?", [$id]);
  Database::query("DELETE FROM rec_instructions WHERE rec_id = ?", [$id]);
  Database::query("DELETE FROM rec_ingr WHERE rec_id = ?", [$id]);
  Database::query("DELETE FROM rec_recipe WHERE rec_id = ?", [$id]);

  Database::commit();
} catch (Exception $e) {
  Database::rollback();
  die_miserable_death("Error deleting recipe: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header("Location: index.php");
exit;

?>
