<?php

include "rec_includes.php";

// Rate limit: 20 photo uploads per hour
enforceRateLimit('upload', 20, 3600);

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
    header("Location: $redirectUrl&error=" . urlencode($msg));
    exit;
  }

  $tmpName = $_FILES['photo']['tmp_name'];
  $originalName = $_FILES['photo']['name'];
  $fileSize = $_FILES['photo']['size'];

  // Check file size (max 10MB)
  if ($fileSize > 10 * 1024 * 1024) {
    header("Location: $redirectUrl&error=" . urlencode("File is too large. Maximum size is 10MB."));
    exit;
  }

  // Validate MIME type using finfo (not the client-supplied type)
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mimeType = $finfo->file($tmpName);

  $allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
  ];

  if (!isset($allowedMimes[$mimeType])) {
    header("Location: $redirectUrl&error=" . urlencode("Invalid file type. Allowed: JPG, PNG, GIF, WebP."));
    exit;
  }

  // Load image and resize if needed (max 1600px on longest side)
  $maxDim = 1600;
  $srcImage = null;
  switch ($mimeType) {
    case 'image/jpeg': $srcImage = imagecreatefromjpeg($tmpName); break;
    case 'image/png':  $srcImage = imagecreatefrompng($tmpName); break;
    case 'image/gif':  $srcImage = imagecreatefromgif($tmpName); break;
    case 'image/webp': $srcImage = imagecreatefromwebp($tmpName); break;
  }

  if (!$srcImage) {
    header("Location: $redirectUrl&error=" . urlencode("Failed to process image."));
    exit;
  }

  $origW = imagesx($srcImage);
  $origH = imagesy($srcImage);

  if ($origW > $maxDim || $origH > $maxDim) {
    if ($origW >= $origH) {
      $newW = $maxDim;
      $newH = (int)round($origH * ($maxDim / $origW));
    } else {
      $newH = $maxDim;
      $newW = (int)round($origW * ($maxDim / $origH));
    }
    $dstImage = imagecreatetruecolor($newW, $newH);

    // Preserve transparency for PNG and WebP
    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
      imagealphablending($dstImage, false);
      imagesavealpha($dstImage, true);
    }

    imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($srcImage);
    $srcImage = $dstImage;
  }

  // Encode resized image to binary
  ob_start();
  switch ($mimeType) {
    case 'image/jpeg': imagejpeg($srcImage, null, 85); break;
    case 'image/png':  imagepng($srcImage, null, 6); break;
    case 'image/gif':  imagegif($srcImage); break;
    case 'image/webp': imagewebp($srcImage, null, 85); break;
  }
  $photoData = ob_get_clean();
  imagedestroy($srcImage);

  if (empty($photoData)) {
    header("Location: $redirectUrl&error=" . urlencode("Failed to process image."));
    exit;
  }

  $fileSize = strlen($photoData);

  // Check if this is the first photo (auto-set as primary)
  $res = BookLogDB::query("SELECT COUNT(*) FROM rec_photo WHERE rec_id = ?", [$recId]);
  $row = BookLogDB::fetchRow($res);
  $isPrimary = ($row[0] == 0) ? 1 : 0;
  BookLogDB::freeResult($res);

  BookLogDB::query(
    "INSERT INTO rec_photo (rec_id, photo_data, original_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?, ?)",
    [$recId, $photoData, $originalName, $mimeType, $fileSize, $isPrimary]
  );

  BookLogDB::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} elseif ($action === 'delete') {

  $photoId = sanitizeInt($_POST['photo_id'] ?? '');
  if (empty($photoId)) {
    header("Location: $redirectUrl");
    exit;
  }

  // Fetch photo record (verify it belongs to this recipe)
  $res = BookLogDB::query("SELECT is_primary FROM rec_photo WHERE photo_id = ? AND rec_id = ?", [$photoId, $recId]);
  $row = BookLogDB::fetchRow($res);
  if (!$row) {
    header("Location: $redirectUrl");
    exit;
  }
  $wasPrimary = $row[0];
  BookLogDB::freeResult($res);

  // Delete DB record
  BookLogDB::query("DELETE FROM rec_photo WHERE photo_id = ?", [$photoId]);

  // If deleted photo was primary, promote the oldest remaining photo
  if ($wasPrimary) {
    $res = BookLogDB::query("SELECT photo_id FROM rec_photo WHERE rec_id = ? ORDER BY created_at ASC LIMIT 1", [$recId]);
    if ($res && ($row = BookLogDB::fetchRow($res))) {
      BookLogDB::query("UPDATE rec_photo SET is_primary = 1 WHERE photo_id = ?", [$row[0]]);
    }
    if ($res) BookLogDB::freeResult($res);
  }

  BookLogDB::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);

  header("Location: $redirectUrl");
  exit;

} elseif ($action === 'set_primary') {

  $photoId = sanitizeInt($_POST['photo_id'] ?? '');
  if (empty($photoId)) {
    header("Location: $redirectUrl");
    exit;
  }

  BookLogDB::beginTransaction();
  try {
    // Clear existing primary
    BookLogDB::query("UPDATE rec_photo SET is_primary = 0 WHERE rec_id = ?", [$recId]);
    // Set new primary
    BookLogDB::query("UPDATE rec_photo SET is_primary = 1 WHERE photo_id = ? AND rec_id = ?", [$photoId, $recId]);
    BookLogDB::query("UPDATE rec_recipe SET rec_last_updated = NOW() WHERE rec_id = ?", [$recId]);
    BookLogDB::commit();
  } catch (Exception $e) {
    BookLogDB::rollback();
    header("Location: $redirectUrl&error=" . urlencode("Failed to set primary photo."));
    exit;
  }

  header("Location: $redirectUrl");
  exit;

} else {
  header("Location: $redirectUrl");
  exit;
}

?>
