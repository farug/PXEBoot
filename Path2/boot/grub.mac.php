<?php
header('Content-Type: text/plain');

// Normalize MAC: lowercase and ":" separators
function norm_mac($raw) {
  $m = strtolower(trim($raw));
  $m = str_replace('-', ':', $m);
  // strip any url-encoded artifacts
  $m = urldecode($m);
  return $m;
}

$mac = isset($_GET['mac']) ? norm_mac($_GET['mac']) : '';
if (!$mac) {
  echo "menuentry 'Error: Missing MAC' { echo 'No MAC provided'; sleep 10 }";
  exit;
}

$ini_path = __DIR__ . '/../api/mac_profiles.ini';
if (!is_file($ini_path)) {
  echo "menuentry 'Server Error' { echo 'mac_profiles.ini missing'; sleep 10 }";
  exit;
}

$ini = parse_ini_file($ini_path, true, INI_SCANNER_TYPED);

if (!isset($ini[$mac])) {
  echo "menuentry 'Unauthorized' { echo 'MAC not registered: $mac'; sleep 10 }";
  exit;
}

$allowed  = isset($ini[$mac]['allowed']) ? strtolower($ini[$mac]['allowed']) : 'no';
$profile  = $ini[$mac]['kickstart'] ?? '';
$repo     = $ini['__global__']['repo']   ?? 'http://192.168.1.10/install';
$kernel   = $ini['__global__']['kernel'] ?? '(http,192.168.1.10)/boot/vmlinuz';
$initrd   = $ini['__global__']['initrd'] ?? '(http,192.168.1.10)/boot/initrd.img';
$ks_base  = $ini['__global__']['ks_base']?? 'http://192.168.1.10/ks/';

if ($allowed !== 'yes') {
  echo "menuentry 'Not Authorized' { echo 'Install not authorized for $mac'; sleep 10 }";
  exit;
}

if (!$profile) {
  echo "menuentry 'Profile Error' { echo 'No kickstart defined for $mac'; sleep 10 }";
  exit;
}

// Compose final URLs
$ks_url = rtrim($ks_base, '/') . '/' . ltrim($profile, '/');

// Optional auto-reset of authorization (one-shot):
if (!empty($ini['__global__']['oneshot']) && $ini['__global__']['oneshot'] === 'yes') {
  $ini[$mac]['allowed'] = 'no';
  // rewrite INI
  $out = '';
  foreach ($ini as $section => $kv) {
    $out .= "[$section]\n";
    foreach ($kv as $k => $v) { $out .= "$k=$v\n"; }
    $out .= "\n";
  }
  file_put_contents($ini_path, $out);
}

echo "menuentry 'Install RHEL 8.10 for $mac' {\n";
echo "  linuxefi $kernel inst.repo=$repo inst.ks=$ks_url inst.nosave=all\n";
echo "  initrdefi $initrd\n";
echo "}\n";

