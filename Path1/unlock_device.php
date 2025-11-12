<?php
/**
 * Unlock Device for PXE Boot
 * This endpoint allows you to temporarily unlock a device for PXE booting
 * 
 * Usage with MAC address:
 *   Unlock device:  curl "http://server/unlock_device.php?action=unlock&mac=XX:XX:XX:XX:XX:XX"
 *   Lock device:    curl "http://server/unlock_device.php?action=lock&mac=XX:XX:XX:XX:XX:XX"
 *   Check status:   curl "http://server/unlock_device.php?action=check&mac=XX:XX:XX:XX:XX:XX"
 * 
 * Usage with hostname:
 *   Unlock device:  curl "http://server/unlock_device.php?action=unlock&hostname=pc1"
 *   Lock device:    curl "http://server/unlock_device.php?action=lock&hostname=pc1"
 *   Check status:   curl "http://server/unlock_device.php?action=check&hostname=pc1"
 * 
 * Unlock all:       curl "http://server/unlock_device.php?action=unlock-all"
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuration
define('LOCK_DIR', __DIR__ . '/locks/');
define('LOCK_TIMEOUT', 300); // 5 minutes default

// Create lock directory if it doesn't exist
if (!is_dir(LOCK_DIR)) {
    mkdir(LOCK_DIR, 0755, true);
}

/**
 * Validate MAC address
 */
function validateMac($mac) {
    $mac = str_replace('-', ':', $mac);
    return preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac);
}

/**
 * Get device configuration
 */
function getDevicesConfig() {
    if (!file_exists(__DIR__ . '/devices.ini')) {
        return [];
    }
    return parse_ini_file(__DIR__ . '/devices.ini', true);
}

/**
 * Convert hostname to MAC address
 */
function hostnameToMac($hostname) {
    $config = getDevicesConfig();
    
    foreach ($config as $macKey => $info) {
        if (isset($info['hostname']) && strtolower($info['hostname']) === strtolower($hostname)) {
            return str_replace('-', ':', $macKey);
        }
    }
    
    return null;
}

/**
 * Get lock file path for a MAC
 */
function getLockFilePath($mac) {
    $macKey = str_replace(':', '-', strtolower($mac));
    return LOCK_DIR . $macKey . '.lock';
}

/**
 * Check if device is unlocked
 */
function isUnlocked($mac) {
    $lockFile = getLockFilePath($mac);
    
    if (!file_exists($lockFile)) {
        return false;
    }
    
    // Check timeout
    $timestamp = file_get_contents($lockFile);
    $time = time() - $timestamp;
    $timeout = LOCK_TIMEOUT;
    
    // Check if timeout has expired
    if ($time > $timeout) {
        // Lock expired
        unlink($lockFile);
        return false;
    }
    
    return true;
}

/**
 * Unlock device
 */
function unlockDevice($mac, $timeout = null) {
    $lockFile = getLockFilePath($mac);
    $timeout = $timeout ?: LOCK_TIMEOUT;
    file_put_contents($lockFile, time() + $timeout);
    return true;
}

/**
 * Lock device
 */
function lockDevice($mac) {
    $lockFile = getLockFilePath($mac);
    if (file_exists($lockFile)) {
        return unlink($lockFile);
    }
    return true;
}

/**
 * Get unlock status
 */
function getUnlockStatus($mac) {
    if (!isUnlocked($mac)) {
        return [
            'unlocked' => false,
            'time_remaining' => 0
        ];
    }
    
    $lockFile = getLockFilePath($mac);
    $timestamp = file_get_contents($lockFile);
    $timeRemaining = max(0, $timestamp - time());
    
    return [
        'unlocked' => true,
        'time_remaining' => $timeRemaining,
        'time_remaining_formatted' => gmdate('H:i:s', $timeRemaining)
    ];
}

// Handle the request
try {
    $action = $_GET['action'] ?? 'check';
    $mac = $_GET['mac'] ?? '';
    $hostname = $_GET['hostname'] ?? '';
    $timeout = isset($_GET['timeout']) ? intval($_GET['timeout']) : null;
    
    // Handle unlock-all
    if ($action === 'unlock-all') {
        $devices = getDevicesConfig();
        if (empty($devices)) {
            throw new Exception('No devices configured');
        }
        
        $results = [];
        
        foreach ($devices as $macKey => $info) {
            $mac = str_replace('-', ':', $macKey);
            if (validateMac($mac)) {
                $results[$mac] = unlockDevice($mac, $timeout);
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'All devices unlocked',
            'devices' => $results
        ]);
        exit;
    }
    
    // Determine MAC address from mac or hostname parameter
    if (!empty($hostname)) {
        $mac = hostnameToMac($hostname);
        if (!$mac) {
            throw new Exception('Hostname not found: ' . $hostname);
        }
    }
    
    // Validate that we have a MAC address
    if (empty($mac)) {
        throw new Exception('Either MAC address or hostname is required');
    }
    
    if (!validateMac($mac)) {
        throw new Exception('Invalid MAC address format: ' . $mac);
    }
    
    // Get hostname for display
    $devices = getDevicesConfig();
    $macKey = str_replace(':', '-', strtolower($mac));
    $deviceInfo = isset($devices[$macKey]) ? $devices[$macKey] : null;
    $displayHostname = $deviceInfo['hostname'] ?? $mac;
    
    // Handle actions
    switch ($action) {
        case 'unlock':
            $result = unlockDevice($mac, $timeout);
            echo json_encode([
                'status' => 'success',
                'message' => 'Device unlocked',
                'mac' => $mac,
                'hostname' => $displayHostname,
                'timeout_seconds' => $timeout ?: LOCK_TIMEOUT
            ]);
            break;
            
        case 'lock':
            $result = lockDevice($mac);
            echo json_encode([
                'status' => 'success',
                'message' => 'Device locked',
                'mac' => $mac,
                'hostname' => $displayHostname
            ]);
            break;
            
        case 'check':
        default:
            $status = getUnlockStatus($mac);
            echo json_encode([
                'status' => 'success',
                'mac' => $mac,
                'hostname' => $displayHostname,
                'unlocked' => $status['unlocked'],
                'time_remaining' => $status['time_remaining'],
                'time_remaining_formatted' => $status['time_remaining_formatted']
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
