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

$ini_path = '/var/www/html/api/mac_profiles.ini';
$ini = parse_ini_file($ini_path, true, INI_SCANNER_TYPED);
$mac = isset($_GET['mac']) ? norm_mac($_GET['mac']) : '';
if (!$mac) {
  echo "menuentry 'Error: Missing MAC' { echo 'No MAC provided'; sleep 10 }";
  foreach (array_keys($ini) as $section) {
      echo " $section\n";
  }
  exit;
}

if (!is_file($ini_path)) {
  echo "menuentry 'Server Error' { echo 'mac_profiles.ini missing'; sleep 10 }";
  exit;
}




if (!isset($ini[$mac])) {
  echo "menuentry 'Unauthorized' { echo 'MAC not registered: $mac'; sleep 10 }";
  exit;
}

$allowed  = isset($ini[$mac]['allowed']) ? strtolower($ini[$mac]['allowed']) : 'no';
// echo "Allowed value = " . $allowed ;
$profile  = $ini[$mac]['kickstart'] ?? '';
// $repo     = $ini['__global__']['repo']   ?? 'http://192.168.10.10/install';
$kernel   = $ini['__global__']['kernel'] ?? '(http,192.168.10.10)/boot/vmlinuz';
echo "kernel : " . $kernel ;
$initrd   = $ini['__global__']['initrd'] ?? '(http,192.168.10.10)/boot/initrd.img';
$ks_base  = $ini['__global__']['ks_base']?? 'http://192.168.10.10/ks/';

if ($allowed !== '1') {
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
echo "  linuxefi $kernel inst.ks=$ks_url inst.nosave=all\n";
echo "  initrdefi $initrd\n";
echo "}\n";

