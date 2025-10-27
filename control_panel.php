<?php
/**
 * PXE Boot Control Panel
 * Web interface to unlock/lock devices for PXE boot
 */

define('DEVICES_CONFIG', __DIR__ . '/devices.ini');
define('LOCK_DIR', __DIR__ . '/locks/');

function getDevices() {
    if (!file_exists(DEVICES_CONFIG)) {
        return [];
    }
    
    $config = parse_ini_file(DEVICES_CONFIG, true);
    $devices = [];
    
    foreach ($config as $macKey => $info) {
        $mac = str_replace('-', ':', $macKey);
        $devices[] = [
            'mac' => $mac,
            'mac_key' => $macKey,
            'hostname' => $info['hostname'] ?? 'Unknown',
            'kickstart' => $info['kickstart'] ?? 'N/A'
        ];
    }
    
    return $devices;
}

function isUnlocked($mac) {
    $macKey = str_replace(':', '-', strtolower($mac));
    $lockFile = LOCK_DIR . $macKey . '.lock';
    
    if (!file_exists($lockFile)) {
        return false;
    }
    
    $timestamp = file_get_contents($lockFile);
    $timeRemaining = max(0, $timestamp - time());
    
    return [
        'unlocked' => true,
        'time_remaining' => $timeRemaining,
        'time_formatted' => gmdate('H:i:s', $timeRemaining)
    ];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $mac = $_GET['mac'] ?? '';
    $hostname = $_GET['hostname'] ?? '';
    
    if ($action === 'unlock') {
        $timeout = isset($_GET['timeout']) ? intval($_GET['timeout']) : 300;
        
        // Build URL with either mac or hostname
        $param = !empty($hostname) ? 'hostname=' . urlencode($hostname) : 'mac=' . urlencode($mac);
        $url = "unlock_device.php?action=unlock&$param&timeout=" . $timeout;
        $response = @file_get_contents($url);
        echo $response ?: json_encode(['status' => 'error', 'message' => 'Failed to unlock']);
        
    } elseif ($action === 'lock') {
        $param = !empty($hostname) ? 'hostname=' . urlencode($hostname) : 'mac=' . urlencode($mac);
        $url = "unlock_device.php?action=lock&$param";
        $response = @file_get_contents($url);
        echo $response ?: json_encode(['status' => 'error', 'message' => 'Failed to lock']);
        
    } elseif ($action === 'get-status') {
        $param = !empty($hostname) ? 'hostname=' . urlencode($hostname) : 'mac=' . urlencode($mac);
        $url = "unlock_device.php?action=check&$param";
        $response = @file_get_contents($url);
        echo $response ?: json_encode(['status' => 'error']);
    }
    
    exit;
}

$devices = getDevices();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PXE Boot Control Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .content { padding: 30px; }
        
        .device-list { list-style: none; }
        
        .device-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            margin: 10px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .device-item.locked { border-left-color: #dc3545; }
        .device-item.unlocked { border-left-color: #28a745; }
        
        .device-info { flex-grow: 1; }
        .device-hostname { font-weight: bold; color: #333; }
        .device-mac { color: #666; font-family: monospace; font-size: 0.9em; margin-top: 5px; }
        
        .device-controls { display: flex; gap: 10px; }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-unlock { background: #28a745; color: white; }
        .btn-lock { background: #dc3545; color: white; }
        .btn-unlock:hover { background: #218838; }
        .btn-lock:hover { background: #c82333; }
        
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .status-unlocked { background: #d4edda; color: #155724; }
        .status-locked { background: #f8d7da; color: #721c24; }
        
        .empty { text-align: center; color: #999; padding: 40px; }
        
        .countdown {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>🔐 PXE Boot Control Panel</h1>
                <p>Unlock devices to allow PXE boot and installation</p>
            </div>
            
            <div class="content">
                <?php if (empty($devices)): ?>
                    <div class="empty">
                        <p>No devices configured</p>
                        <p style="margin-top: 10px;">Add devices to devices.ini</p>
                    </div>
                <?php else: ?>
                    <ul class="device-list" id="deviceList">
                        <?php foreach ($devices as $device): ?>
                            <li class="device-item locked" id="device-<?= $device['mac'] ?>">
                                <div class="device-info">
                                    <div class="device-hostname"><?= htmlspecialchars($device['hostname']) ?></div>
                                    <div class="device-mac"><?= htmlspecialchars($device['mac']) ?></div>
                                    <span class="status-badge status-locked">LOCKED</span>
                                    <div class="countdown" id="countdown-<?= $device['mac'] ?>"></div>
                                </div>
                                <div class="device-controls">
                                    <button class="btn btn-unlock" onclick="unlockDevice('<?= $device['mac'] ?>')">Unlock</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function updateDeviceStatus(mac) {
            fetch(`?action=get-status&mac=${mac}`)
                .then(r => r.json())
                .then(data => {
                    const item = document.getElementById(`device-${mac}`);
                    const countdown = document.getElementById(`countdown-${mac}`);
                    
                    if (data.unlocked && data.time_remaining > 0) {
                        item.className = 'device-item unlocked';
                        item.querySelector('.status-badge').className = 'status-badge status-unlocked';
                        item.querySelector('.status-badge').textContent = 'UNLOCKED';
                        item.querySelector('.device-controls').innerHTML = `<button class="btn btn-lock" onclick="lockDevice('${mac}')">Lock</button>`;
                        countdown.textContent = `Time remaining: ${data.time_remaining_formatted}`;
                    } else {
                        item.className = 'device-item locked';
                        item.querySelector('.status-badge').className = 'status-badge status-locked';
                        item.querySelector('.status-badge').textContent = 'LOCKED';
                        item.querySelector('.device-controls').innerHTML = `<button class="btn btn-unlock" onclick="unlockDevice('${mac}')">Unlock</button>`;
                        countdown.textContent = '';
                    }
                });
        }
        
        function unlockDevice(mac) {
            fetch(`?action=unlock&mac=${mac}&timeout=300`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateDeviceStatus(mac);
                        setInterval(() => updateDeviceStatus(mac), 5000);
                    }
                });
        }
        
        function lockDevice(mac) {
            fetch(`?action=lock&mac=${mac}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateDeviceStatus(mac);
                    }
                });
        }
        
        // Initial status check
        <?php foreach ($devices as $device): ?>
        updateDeviceStatus('<?= $device['mac'] ?>');
        setInterval(() => updateDeviceStatus('<?= $device['mac'] ?>'), 5000);
        <?php endforeach; ?>
    </script>
</body>
</html>
