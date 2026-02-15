<?php
if ( empty ( $PHP_SELF ) && ! empty ( $_SERVER ) &&
  ! empty ( $_SERVER['PHP_SELF'] ) ) {
  $PHP_SELF = $_SERVER['PHP_SELF'];
}
if ( ! empty ( $PHP_SELF ) && preg_match ( "/\/includes\//", $PHP_SELF ) ) {
    die ( "You can't access this file directly!" );
}

// Establish a database connection using PDO.
if ( empty ( $c ) ) {
  $c = BookLogDB::connect();
  if ( ! $c ) {
    die_miserable_death (
      "Error connecting to database:<blockquote>" .
      BookLogDB::error() . "</blockquote>\n" );
  }
}

if ( empty ( $PHP_SELF ) )
  $PHP_SELF = $_SERVER["PHP_SELF"];

?>
