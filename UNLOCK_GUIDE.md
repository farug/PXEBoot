# Device Unlock System - Complete Guide

## Overview

Your PXE boot system now includes a **unlock/lock mechanism**. Devices are **LOCKED by default** and **cannot boot into PXE installation** until you explicitly unlock them via curl or the web interface.

This provides **security and control** over when installations happen.

## How It Works

```
Device tries to PXE boot
         ↓
    boot.php checks
    if device is unlocked
         ↓
    ├─ LOCKED: Shows message and exits
    └─ UNLOCKED: Proceeds with installation (and consumes unlock)
```

### Key Features

1. **Locked by default** - No unauthorized boots
2. **curl unlock** - Remote control
3. **Web interface** - Visual control panel
4. **Time-limited** - Locks expire after 5 minutes
5. **One-time use** - Lock is consumed after successful boot
6. **Automatic logging** - All attempts logged

## curl Commands

### Unlock a Device

```bash
# Unlock for default 5 minutes
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"

# Unlock for custom time (in seconds)
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55&timeout=600"
```

### Lock a Device (Prevent Boot)

```bash
curl "http://192.168.10.12/unlock_device.php?action=lock&mac=00:11:22:33:44:55"
```

### Check Unlock Status

```bash
curl "http://192.168.10.12/unlock_device.php?action=check&mac=00:11:22:33:44:55"
```

**Response:**
```json
{
  "status": "success",
  "mac": "00:11:22:33:44:55",
  "unlocked": true,
  "time_remaining": 240,
  "time_remaining_formatted": "00:04:00"
}
```

### Unlock All Devices at Once

```bash
curl "http://192.168.10.12/unlock_device.php?action=unlock-all"
```

## Complete Workflow Example

### 1. Check Current Status
```bash
# Check if device is unlocked
curl "http://192.168.10.12/unlock_device.php?action=check&mac=00:11:22:33:44:55"
```

### 2. Unlock the Device
```bash
# Unlock device for 5 minutes
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
```

### 3. Device Boots
The device will now successfully PXE boot (if configured to boot from network).

### 4. Lock is Consumed
After successful boot, the lock is automatically consumed and removed.

### 5. Device is Locked Again
Next attempt will require another unlock.

## Web Interface

Access the web control panel:

```
http://192.168.10.12/control_panel.php
```

Features:
- Visual status of all devices
- One-click unlock/lock
- Real-time countdown timer
- Beautiful modern UI

## Lock Behavior

### Time Limits

- **Default:** 5 minutes (300 seconds)
- **Custom:** Specify `timeout` parameter in seconds
- **Expiration:** Lock automatically expires after timeout
- **Cleanup:** Expired locks are automatically removed

### One-Time Use

- Lock is **consumed** after first successful boot
- Each boot requires a new unlock
- Prevents multiple installations without explicit permission

### Persistent State

- Locks are stored as files in `locks/` directory
- Lock files contain expiration timestamps
- Removed automatically after use or expiration

## Security Benefits

✅ **Prevent Accidental Boots**
- No unexpected installations
- Devices won't boot even if PXE is enabled in BIOS

✅ **Controlled Deployment**
- Explicit unlock required for each installation
- Audit trail via logs

✅ **Time-Limited Access**
- Locks expire automatically
- Reduces security exposure window

✅ **Remote Control**
- Unlock from anywhere with curl
- No physical access required

✅ **Multi-Device Management**
- Unlock devices individually or in bulk
- Granular control over deployment

## Response Codes

### Successful Unlock
```json
{
  "status": "success",
  "message": "Device unlocked",
  "mac": "00:11:22:33:44:55",
  "timeout_seconds": 300
}
```

### Device Already Unlocked
Lock is extended with new timeout.

### Lock Expired
Lock is removed and needs to be recreated.

### Error: Invalid MAC
```json
{
  "status": "error",
  "message": "Invalid MAC address format: invalid"
}
```

## PowerShell Examples (Windows)

### Unlock Device
```powershell
$mac = "00:11:22:33:44:55"
$server = "192.168.10.12"
Invoke-WebRequest -Uri "http://$server/unlock_device.php?action=unlock&mac=$mac"
```

### Check Status
```powershell
$response = Invoke-WebRequest -Uri "http://$server/unlock_device.php?action=check&mac=$mac"
$json = $response.Content | ConvertFrom-Json
Write-Host "Unlocked: $($json.unlocked)"
Write-Host "Time Remaining: $($json.time_remaining_formatted)"
```

## Integration Examples

### Bash Script
```bash
#!/bin/bash
# deploy_server.sh

MAC="00:11:22:33:44:55"
SERVER="192.168.10.12"

echo "Unlocking device $MAC..."
curl -s "http://$SERVER/unlock_device.php?action=unlock&mac=$MAC" > /dev/null

echo "Device unlocked. Waiting for boot..."
sleep 10

echo "Checking status..."
curl -s "http://$SERVER/unlock_device.php?action=check&mac=$MAC" | jq

echo "Installation should now proceed."
```

### Python Script
```python
import requests

def unlock_device(server_ip, mac):
    url = f"http://{server_ip}/unlock_device.php"
    params = {'action': 'unlock', 'mac': mac}
    response = requests.get(url, params=params)
    return response.json()

def check_status(server_ip, mac):
    url = f"http://{server_ip}/unlock_device.php"
    params = {'action': 'check', 'mac': mac}
    response = requests.get(url, params=params)
    return response.json()

# Usage
result = unlock_device('192.168.10.12', '00:11:22:33:44:55')
print(result)

status = check_status('192.168.10.12', '00:11:22:33:44:55')
print(f"Unlocked: {status['unlocked']}")
print(f"Time remaining: {status['time_remaining_formatted']}")
```

## What Happens When Device is Locked?

When a device tries to PXE boot but is locked, it receives this message:

```
Device is LOCKED for PXE boot
MAC: 00:11:22:33:44:55
========================================
This device requires explicit unlock
Unlock via: curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
or visit: http://192.168.10.12/control_panel.php
========================================
```

The boot then exits without proceeding to installation.

## Troubleshooting

### Lock Not Working
- Check `locks/` directory permissions
- Verify web server can write to locks directory
- Check PHP file permissions

### Lock Expires Too Quickly
- Increase timeout: `&timeout=600` (10 minutes)
- Re-unlock before it expires

### Multiple Boots Without Permission
- Ensure lock is one-time use (automatic)
- Check boot.log for multiple unlock attempts
- Review lock files in `locks/` directory

### Cannot Unlock Device
- Verify MAC address format (XX:XX:XX:XX:XX:XX)
- Check web server logs
- Ensure PHP is working
- Verify file permissions

## File Structure

```
PXEBoot/
├── boot.php                 # Checks unlock status before booting
├── unlock_device.php        # API for unlock/lock operations
├── control_panel.php        # Web interface for visual control
├── devices.ini              # Device configuration
├── locks/                   # Lock files directory (auto-created)
│   ├── 00-11-22-33-44-55.lock
│   └── 66-77-88-99-aa-bb.lock
└── boot.log                 # Boot attempt logs
```

## Logging

All unlock attempts and boot attempts are logged to `boot.log`:

```
[2024-01-15 10:23:45] MAC: 00:11:22:33:44:55 | Hostname: N/A | Status: LOCKED | IP: 192.168.10.101
[2024-01-15 10:24:12] MAC: 00:11:22:33:44:55 | Hostname: pc1 | Status: SUCCESS | IP: 192.168.10.101
```

## API Summary

| Endpoint | Action | Parameters | Description |
|----------|--------|------------|-------------|
| `/unlock_device.php` | `unlock` | `mac`, `timeout` | Unlock device for PXE boot |
| `/unlock_device.php` | `lock` | `mac` | Lock device (prevent boot) |
| `/unlock_device.php` | `check` | `mac` | Check unlock status |
| `/unlock_device.php` | `unlock-all` | - | Unlock all devices |
| `/control_panel.php` | - | - | Web interface |

## Best Practices

1. **Unlock Only When Needed**
   - Don't unlock devices prematurely
   - Use short timeouts for security

2. **Monitor Logs**
   - Check boot.log regularly
   - Watch for unauthorized attempts

3. **Use Timeouts Wisely**
   - Production: 5 minutes
   - Development: 30 minutes
   - Emergency: 1 hour

4. **Automate with Scripts**
   - Create wrapper scripts for common tasks
   - Integrate with deployment tools

5. **Regular Cleanup**
   - Expired locks auto-remove
   - Manually check locks/ directory if needed

## Next Steps

1. Test the unlock system with a VM
2. Create deployment automation scripts
3. Integrate with your deployment pipeline
4. Set up monitoring and alerting
