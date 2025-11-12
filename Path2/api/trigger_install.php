<?php
header('Content-Type: text/plain');

$ini_file = __DIR__ . '/mac_profiles.ini';
$token_expected = getenv('INSTALL_TRIGGER_TOKEN') ?: '';  // optional

// Require ?mac= and optional ?token=
if (!isset($_GET['mac'])) { http_response_code(400); die("Missing mac\n"); }
$mac = strtolower(str_replace('-', ':', trim($_GET['mac'])));

if ($token_expected !== '') {
  $token = $_GET['token'] ?? '';
  if (!hash_equals($token_expected, $token)) {
    http_response_code(403); die("Forbidden\n");
  }
}

if (!is_file($ini_file)) { http_response_code(500); die("mac_profiles.ini missing\n"); }

$cfg = parse_ini_file($ini_file, true, INI_SCANNER_RAW);
if (!isset($cfg[$mac])) { http_response_code(404); die("MAC not found\n"); }

$cfg[$mac]['allowed'] = 'yes';

// Write back INI
$content = '';
foreach ($cfg as $section => $kv) {
  $content .= "[$section]\n";
  foreach ($kv as $k => $v) { $content .= "$k=$v\n"; }
  $content .= "\n";
}
file_put_contents($ini_file, $content);

echo "Installation authorized for $mac\n";

