<?php
/**
 * Migration 04: Store recipe titles and sources as plain text.
 *
 * edit_handler.php used to run htmlspecialchars() on the title, source and
 * category before saving, while every page also escapes on output. Titles
 * like "M&M" were therefore stored as "M&amp;M", and each re-save escaped
 * them again ("M&amp;amp;M"). This decodes those columns back to plain text.
 *
 * Safe to re-run: only rows that still contain an entity htmlspecialchars()
 * produces are touched, and decoding repeats until the value stops changing
 * so multiply-escaped values are fully undone.
 */

use Recipes\Database\Database;

$entityPattern = '&(amp|quot|lt|gt|#0?39);';

$res = Database::query(
    "SELECT rec_id, rec_title, rec_source, rec_category FROM rec_recipe
     WHERE rec_title REGEXP ? OR rec_source REGEXP ? OR rec_category REGEXP ?",
    [$entityPattern, $entityPattern, $entityPattern]
);

$rows = [];
while ($row = Database::fetchRow($res)) {
    $rows[] = $row;
}
Database::freeResult($res);

$decode = function (?string $value): ?string {
    if ($value === null) {
        return null;
    }
    do {
        $previous = $value;
        $value = htmlspecialchars_decode($value, ENT_QUOTES);
    } while ($value !== $previous);
    return $value;
};

foreach ($rows as $row) {
    Database::query(
        "UPDATE rec_recipe SET rec_title = ?, rec_source = ?, rec_category = ? WHERE rec_id = ?",
        [$decode($row[1]), $decode($row[2]), $decode($row[3]), $row[0]]
    );
}

echo "     Decoded HTML entities in " . count($rows) . " recipe(s).\n";
