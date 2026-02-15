<?php

include "rec_includes.php";

// Validate CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
  die_miserable_death("Invalid CSRF token.");
}

$id = sanitizeInt($_POST['id'] ?? '');
$isNew = empty($id);

$recTitle = sanitizeString($_POST['title'] ?? '');
$recSource = sanitizeString($_POST['source'] ?? '');
$recUrl = trim($_POST['url'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');

if (empty($recTitle)) {
  die_miserable_death("Error: Title is required.");
}

$now = date('Y-m-d H:i:s');

BookLogDB::beginTransaction();

try {
  if ($isNew) {
    // Get next ID
    $res = BookLogDB::query("SELECT MAX(rec_id) FROM rec_recipe");
    $row = BookLogDB::fetchRow($res);
    $id = ($row && $row[0]) ? $row[0] + 1 : 1;
    BookLogDB::freeResult($res);

    // Insert recipe
    BookLogDB::query(
      "INSERT INTO rec_recipe (rec_id, rec_title, rec_last_updated, rec_date_added, rec_source, rec_url) VALUES (?, ?, ?, ?, ?, ?)",
      [$id, $recTitle, $now, $now, $recSource, $recUrl]
    );
  } else {
    // Update recipe
    BookLogDB::query(
      "UPDATE rec_recipe SET rec_title = ?, rec_last_updated = ?, rec_source = ?, rec_url = ? WHERE rec_id = ?",
      [$recTitle, $now, $recSource, $recUrl, $id]
    );

    // Delete existing ingredients and instructions (will reinsert)
    BookLogDB::query("DELETE FROM rec_ingr WHERE rec_id = ?", [$id]);
    BookLogDB::query("DELETE FROM rec_instructions WHERE rec_id = ?", [$id]);
  }

  // Insert ingredients
  $qtys = $_POST['qty'] ?? [];
  $units = $_POST['unit'] ?? [];
  $names = $_POST['ingredient'] ?? [];
  $preps = $_POST['prep'] ?? [];

  $ingrNum = 1;
  for ($i = 0; $i < count($names); $i++) {
    $name = trim($names[$i] ?? '');
    if (empty($name)) continue;

    $qty = trim($qtys[$i] ?? '');
    $unit = trim($units[$i] ?? '');
    $prep = trim($preps[$i] ?? '');

    // Pass NULL for empty quantity (FLOAT column rejects empty strings in strict mode)
    $qtyParam = ($qty !== '') ? $qty : null;

    BookLogDB::query(
      "INSERT INTO rec_ingr (rec_id, rec_ingr_num, rec_quantity, rec_quantity_type, rec_name, rec_prep) VALUES (?, ?, ?, ?, ?, ?)",
      [$id, $ingrNum, $qtyParam, $unit, $name, $prep]
    );
    $ingrNum++;
  }

  // Insert instructions
  if (!empty($instructions)) {
    BookLogDB::query(
      "INSERT INTO rec_instructions (rec_id, rec_instructions) VALUES (?, ?)",
      [$id, $instructions]
    );
  }

  BookLogDB::commit();
} catch (Exception $e) {
  BookLogDB::rollback();
  die_miserable_death("Error saving recipe: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// If an image URL was provided (from import), download and store it as a photo
$imageUrl = trim($_POST['image_url'] ?? '');
if (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $imageUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)',
    CURLOPT_SSL_VERIFYPEER => true,
  ]);
  $imageData = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);

  if ($imageData !== false && $httpCode >= 200 && $httpCode < 400) {
    // Validate it's actually an image using finfo on the raw data
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (in_array($mimeType, $allowedMimes)) {
      // Resize if needed (max 1600px)
      $maxDim = 1600;
      $srcImage = imagecreatefromstring($imageData);
      if ($srcImage) {
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
          if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
          }
          imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
          imagedestroy($srcImage);
          $srcImage = $dstImage;
        }

        ob_start();
        switch ($mimeType) {
          case 'image/jpeg': imagejpeg($srcImage, null, 85); break;
          case 'image/png':  imagepng($srcImage, null, 6); break;
          case 'image/gif':  imagegif($srcImage); break;
          case 'image/webp': imagewebp($srcImage, null, 85); break;
        }
        $photoData = ob_get_clean();
        imagedestroy($srcImage);

        if (!empty($photoData)) {
          $fileSize = strlen($photoData);
          $originalName = basename(parse_url($imageUrl, PHP_URL_PATH)) ?: 'imported.jpg';
          BookLogDB::query(
            "INSERT INTO rec_photo (rec_id, photo_data, original_name, mime_type, file_size, is_primary) VALUES (?, ?, ?, ?, ?, 1)",
            [$id, $photoData, $originalName, $mimeType, $fileSize]
          );
        }
      }
    }
  }
}

header("Location: view.php?id=" . (int)$id);
exit;

?>
