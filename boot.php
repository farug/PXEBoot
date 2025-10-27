<?php
/**
 * PXE Boot Script for iPXE
 * Provides dynamic boot configuration based on MAC address
 */

// Set response headers
header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Configuration
define('DEVICES_CONFIG', __DIR__ . '/devices.ini');
define('LOG_FILE', __DIR__ . '/boot.log');
define('KICKSTART_DIR', '/kickstarts/');
define('RHEL_DIR', '/rhel/');
define('LOCK_DIR', __DIR__ . '/locks/');
define('LOCK_TIMEOUT', 300); // 5 minutes default

/**
 * Log boot attempts
 */
function logBoot($mac, $hostname = null, $status = 'UNKNOWN') {
    $timestamp = date('Y-m-d H:i:s');
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = sprintf(
        "[%s] MAC: %s | Hostname: %s | Status: %s | IP: %s\n",
        $timestamp,
        $mac,
        $hostname ?? 'N/A',
        $status,
        $remoteAddr
    );
    
    // Append to log file
    if (is_writable(dirname(LOG_FILE)) || file_exists(LOG_FILE) && is_writable(LOG_FILE)) {
        file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
    }
}

/**
 * Validate MAC address format
 */
function validateMac($mac) {
    // Allow formats: XX:XX:XX:XX:XX:XX or XX-XX-XX-XX-XX-XX
    $mac = str_replace('-', ':', $mac);
    return preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac);
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get configuration from INI file
 */
function getDevicesConfig() {
    if (!file_exists(DEVICES_CONFIG)) {
        throw new Exception('devices.ini not found');
    }
    
    $config = parse_ini_file(DEVICES_CONFIG, true);
    if ($config === false) {
        throw new Exception('Failed to parse devices.ini');
    }
    
    return $config;
}

/**
 * Check if device is unlocked for PXE boot
 */
function isDeviceUnlocked($mac) {
    $macKey = str_replace(':', '-', strtolower($mac));
    $lockFile = LOCK_DIR . $macKey . '.lock';
    
    // Create lock directory if it doesn't exist
    if (!is_dir(LOCK_DIR)) {
        mkdir(LOCK_DIR, 0755, true);
    }
    
    if (!file_exists($lockFile)) {
        return false;
    }
    
    // Check timeout - lock expires after LOCK_TIMEOUT seconds
    $timestamp = file_get_contents($lockFile);
    $timeRemaining = $timestamp - time();
    
    // Check if lock has expired
    if ($timeRemaining <= 0) {
        unlink($lockFile);
        return false;
    }
    
    // Consume the lock (remove it after checking so it's one-time use)
    unlink($lockFile);
    return true;
}

try {
    // Get MAC address from iPXE
    $mac = $_GET['mac'] ?? '';
    
    if (empty($mac)) {
        echo "#!ipxe\n";
        echo "echo No MAC address provided\n";
        echo "exit\n";
        logBoot('NONE', null, 'NO_MAC');
        exit(1);
    }
    
    // Sanitize and normalize MAC address
    $mac = strtolower(trim($mac));
    
    // Validate MAC format
    if (!validateMac($mac)) {
        echo "#!ipxe\n";
        echo "echo Invalid MAC address format: {$mac}\n";
        echo "exit\n";
        logBoot($mac, null, 'INVALID_MAC');
        exit(1);
    }
    
    // Convert to dash format for INI lookup
    $macKey = str_replace(':', '-', $mac);
    
    // Load device configuration
    $devices = getDevicesConfig();
    
    // Check if device is registered
    if (!isset($devices[$macKey])) {
        echo "#!ipxe\n";
        echo "echo Unknown MAC: {$mac}\n";
        echo "exit\n";
        logBoot($mac, null, 'NOT_REGISTERED');
        exit(1);
    }
    
    // Check if device is unlocked for PXE boot
    if (!isDeviceUnlocked($mac)) {
        echo "#!ipxe\n";
        echo "echo Device is LOCKED for PXE boot\n";
        echo "echo MAC: {$mac}\n";
        echo "echo ========================================\n";
        echo "echo This device requires explicit unlock\n";
        echo "echo Unlock via: curl \"http://192.168.10.12/unlock_device.php?action=unlock&mac={$mac}\"\n";
        echo "echo or visit: http://192.168.10.12/control_panel.php\n";
        echo "echo ========================================\n";
        echo "exit\n";
        logBoot($mac, null, 'LOCKED');
        exit(1);
    }
    
    // Get device info
    $info = $devices[$macKey];
    $hostname = sanitizeInput($info['hostname'] ?? 'unknown');
    $kickstartFile = sanitizeInput($info['kickstart'] ?? '');
    
    if (empty($kickstartFile)) {
        echo "#!ipxe\n";
        echo "echo Kickstart file not configured for {$mac}\n";
        echo "exit\n";
        logBoot($mac, $hostname, 'NO_KICKSTART');
        exit(1);
    }
    
    // Build URLs
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
    $kickstartUrl = $baseUrl . KICKSTART_DIR . $kickstartFile;
    $kernelUrl = $baseUrl . RHEL_DIR . 'vmlinuz';
    $initrdUrl = $baseUrl . RHEL_DIR . 'initrd.img';
    
    // Generate iPXE script
    $ipxeScript = <<<IPXE
#!ipxe
echo ========================================
echo Booting hostname: {$hostname}
echo MAC address: {$mac}
echo Kickstart: {$kickstartUrl}
echo ========================================

# Set timeouts
set menu-timeout 5000

# Load kernel with kickstart parameters
kernel {$kernelUrl} inst.ks={$kickstartUrl} hostname={$hostname} inst.text inst.stage2=hd:LABEL=RHEL-8-0-0-BaseOS-x86_64

# Load initial ramdisk
initrd {$initrdUrl}

# Boot the system
boot

IPXE;
    
    // Output the iPXE script
    echo $ipxeScript;
    
    // Log successful boot attempt
    logBoot($mac, $hostname, 'SUCCESS');
    
} catch (Exception $e) {
    // Log error
    logBoot($mac ?? 'ERROR', null, 'EXCEPTION: ' . $e->getMessage());
    
    echo "#!ipxe\n";
    echo "echo Error: {$e->getMessage()}\n";
    echo "exit\n";
    exit(1);
}
?>
