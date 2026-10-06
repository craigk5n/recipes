<?php

include "rec_includes.php";

use Recipes\Database\Database;
use Recipes\Security\Security;
use Recipes\Media\ImageProcessor;
use function Recipes\Auth\getAuthManager;

// Detect if POST data was silently dropped due to exceeding post_max_size.
// When this happens, $_POST and $_FILES are both empty, which causes a
// confusing "Invalid CSRF token" error instead of a useful file size message.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
  $maxSize = ini_get('post_max_size');
  // Go back to the page the upload came from, but only if it is one of ours.
  $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '');
  $back = null;
  $ourHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
  if (is_array($referer) && isset($referer['host']) && $referer['host'] === $ourHost) {
    $back = basename($referer['path'] ?? '') . (isset($referer['query']) ? '?' . $referer['query'] : '');
  }
  Security::flashError("Upload failed: file exceeds the maximum size ({$maxSize}). Please choose a smaller file.");
  header("Location: " . Security::localRedirect($back));
  exit;
}

// Rate limit: 20 photo uploads per hour
enforceRateLimit('upload', 20, 3600);

if (!getAuthManager()->can('edit')) {
  die_miserable_death("Unauthorized.");
}

// Validate CSRF token. If it fails, the user most likely loaded the form a long
// time ago and their session expired — redirect them back to the recipe with a
// friendly "please try again" message instead of a bare 500 page. We need the
// recipe id for a useful redirect, so resolve it first.
$action = $_POST['action'] ?? '';
$recId = sanitizeInt($_POST['rec_id'] ?? '');

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  $target = !empty($recId) ? ("view.php?id=" . (int)$recId) : "index.php";
  Security::flashError("Your session expired. Please reload the page and try uploading again.");
  header("Location: " . $target);
  exit;
}

if (empty($recId)) {
  header("Location: index.php");
  exit;
}

if (!getAuthManager()->can('edit', (int)$recId)) {
    die_miserable_death("Unauthorized.");
}

// Verify recipe exists
$res = Database::query("SELECT rec_id FROM rec_recipe WHERE rec_id = ?", [$recId]);
if (!$res || !Database::fetchRow($res)) {
  header("Location: index.php");
  exit;
}
Database::freeResult($res);

$redirectUrl = "view.php?id=" . (int)$recId;

if ($action === 'upload') {

  // Validate file upload
  if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $errorCode = $_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = 'Upload failed.';
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
      $msg = 'File is too large.';
    } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
      $msg = 'No file selected.';
    }
    Security::flashError($msg);
    header("Location: $redirectUrl");
    exit;
  }

  $tmpName = $_FILES['photo']['tmp_name'];
  $originalName = $_FILES['photo']['name'];
  $fileSize = $_FILES['photo']['size'];

  // Check file size (max 10MB)
  if ($fileSize > 10 * 1024 * 1024) {
    Security::flashError("File is too large. Maximum size is 10MB.");
    header("Location: $redirectUrl");
    exit;
  }

  // Validate by content (not the client-supplied type), refuse oversized
  // canvases, and re-encode so only pixel data is stored.
  $photo = ImageProcessor::normalize((string)file_get_contents($tmpName));
  if ($photo === null) {
    Security::flashError("Invalid image. Allowed: JPG, PNG, GIF, WebP.");
    header("Location: $redirectUrl");
    exit;
  }
  $photoData = $photo['data'];
  $mimeType = $photo['mime'];
  $fileSize = strlen($photoData);

  // Check if this is the first photo (auto-set as primary)
  $res = Database::query("SELECT COUNT(*) FROM rec_photo WHERE rec_id = ?", [$recId]);
  $row = Database::fetchRow($res);
  $isPrimary = ($row[0] == 0) ? 1 : 0;
  Database::freeResult($res);

  Database::query(
    "INSERT INTO rec_photo (rec_id, photo_data, original_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?, ?)",
    [$recId, $photoData, $originalName, $mimeType, $fileSize, $isPrimary]
  );

  Database::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} elseif ($action === 'delete') {

  $photoId = sanitizeInt($_POST['photo_id'] ?? '');
  if (empty($photoId)) {
    header("Location: $redirectUrl");
    exit;
  }

  // Fetch photo record (verify it belongs to this recipe)
  $res = Database::query("SELECT is_primary FROM rec_photo WHERE photo_id = ? AND rec_id = ?", [$photoId, $recId]);
  $row = Database::fetchRow($res);
  if (!$row) {
    header("Location: $redirectUrl");
    exit;
  }
  $wasPrimary = $row[0];
  Database::freeResult($res);

  // Delete DB record
  Database::query("DELETE FROM rec_photo WHERE photo_id = ?", [$photoId]);

  // If deleted photo was primary, promote the oldest remaining photo
  if ($wasPrimary) {
    $res = Database::query("SELECT photo_id FROM rec_photo WHERE rec_id = ? ORDER BY created_at ASC LIMIT 1", [$recId]);
    if ($res && ($row = Database::fetchRow($res))) {
      Database::query("UPDATE rec_photo SET is_primary = 1 WHERE photo_id = ?", [$row[0]]);
    }
    if ($res) Database::freeResult($res);
  }

  Database::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} elseif ($action === 'set_primary') {

  $photoId = sanitizeInt($_POST['photo_id'] ?? '');
  if (empty($photoId)) {
    header("Location: $redirectUrl");
    exit;
  }

  Database::beginTransaction();
  try {
    // Clear existing primary
    Database::query("UPDATE rec_photo SET is_primary = 0 WHERE rec_id = ?", [$recId]);
    // Set new primary
    Database::query("UPDATE rec_photo SET is_primary = 1 WHERE photo_id = ? AND rec_id = ?", [$photoId, $recId]);
    Database::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);
    Database::commit();
  } catch (Exception $e) {
    Database::rollback();
    Security::flashError("Failed to set primary photo.");
    header("Location: $redirectUrl");
    exit;
  }

  header("Location: $redirectUrl");
  exit;

} else {
  header("Location: $redirectUrl");
  exit;
}

?>
