<?php
header('Content-Type: text/plain');

$ini_path = '/var/www/html/api/mac_profiles.ini';

echo "INI path: $ini_path\n\n";

if (!is_file($ini_path)) {
    echo "File does not exist\n";
    exit;
}

if (!is_readable($ini_path)) {
    echo "File exists but is not readable by PHP/Apache\n";
    exit;
}

$ini = parse_ini_file($ini_path, true, INI_SCANNER_TYPED);

if ($ini === false) {
    echo "parse_ini_file failed\n";
    exit;
}

echo "Parsed INI:\n";
var_dump($ini);

echo "\nSection names:\n";
var_dump(array_keys($ini));
