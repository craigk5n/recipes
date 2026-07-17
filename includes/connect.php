<?php

if (
    empty($PHP_SELF) && ! empty($_SERVER) &&
    ! empty($_SERVER['PHP_SELF'])
) {
    $PHP_SELF = $_SERVER['PHP_SELF'];
}
if (! empty($PHP_SELF) && preg_match("/\/includes\//", $PHP_SELF)) {
    die("You can't access this file directly!");
}

use Recipes\Database\Database;

// Establish a database connection using PDO. Database::connect() either returns
// a live PDO or reports the failure and halts on its own, so there is no falsy
// return left for this file to check.
if (empty($c)) {
    $c = Database::connect();
}

if (empty($PHP_SELF)) {
    $PHP_SELF = $_SERVER["PHP_SELF"];
}
