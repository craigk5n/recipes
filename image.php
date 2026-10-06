<?php

include "rec_includes.php";

use Recipes\Database\Database;
use Recipes\Media\ImageProcessor;

$photoId = sanitizeInt($_GET['id'] ?? '');
if (empty($photoId)) {
  http_response_code(404);
  exit;
}

$res = Database::query("SELECT photo_data, mime_type, file_size FROM rec_photo WHERE photo_id = ?", [$photoId]);
if (!$res) {
  http_response_code(500);
  exit;
}
$row = Database::fetchRow($res);
if (!$row) {
  http_response_code(404);
  exit;
}

$photoData = $row[0];
$mimeType = $row[1];
$fileSize = $row[2];
Database::freeResult($res);

// Only ever serve image types, whatever is in the row, so stored bytes can
// never be rendered as HTML or script.
if (!in_array($mimeType, ImageProcessor::ALLOWED_MIMES, true)) {
  http_response_code(404);
  exit;
}

header("Content-Type: $mimeType");
header("Content-Length: " . strlen($photoData));
header("Content-Disposition: inline");
header("Cache-Control: private, max-age=86400");
echo $photoData;
exit;

?>
