<?php

include "rec_includes.php";

$photoId = sanitizeInt($_GET['id'] ?? '');
if (empty($photoId)) {
  http_response_code(404);
  exit;
}

$res = BookLogDB::query("SELECT photo_data, mime_type, file_size FROM rec_photo WHERE photo_id = ?", [$photoId]);
if (!$res) {
  http_response_code(500);
  exit;
}
$row = BookLogDB::fetchRow($res);
if (!$row) {
  http_response_code(404);
  exit;
}

$photoData = $row[0];
$mimeType = $row[1];
$fileSize = $row[2];
BookLogDB::freeResult($res);

header("Content-Type: $mimeType");
header("Content-Length: $fileSize");
header("Cache-Control: public, max-age=86400");
echo $photoData;
exit;

?>
