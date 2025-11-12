# PXE Boot System - Summary of Improvements

## What Was Done

### 1. Security: Device Unlock/Lock System ⭐

**NEW FEATURE:** Devices are now **LOCKED by default** and require explicit unlock via curl or web interface before PXE boot will proceed.

**Why This is Important:**
- Prevents accidental installations
- Provides control over when deployments happen
- Security through explicit authorization
- Audit trail via logging

### 2. New Files Created

| File | Purpose |
|------|---------|
| `unlock_device.php` | REST API for unlock/lock operations |
| `control_panel.php` | Beautiful web interface for device management |
| `UNLOCK_GUIDE.md` | Complete documentation for unlock system |
| `SUMMARY.md` | This file |

### 3. Modified Files

| File | Changes |
|------|---------|
| `boot.php` | Now checks if device is unlocked before booting |
| `.gitignore` | Added `locks/` directory to ignore list |

## How It Works

```
┌─────────────────┐
│  Device PXE     │
│  Boot Attempt   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ boot.php checks  │
│ if unlocked?    │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ↓         ↓
LOCKED    UNLOCKED
    │         │
    │         ↓
    │    Installation
    │    Proceeds
    ↓
Shows Message
and Exits
```

## Quick Commands

### Unlock Device (Required for Boot)

**By MAC address:**
```bash
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
```

**By hostname (NEW!):**
```bash
curl "http://192.168.10.12/unlock_device.php?action=unlock&hostname=pc1"
```

### Check Status

**By MAC address:**
```bash
curl "http://192.168.10.12/unlock_device.php?action=check&mac=00:11:22:33:44:55"
```

**By hostname:**
```bash
curl "http://192.168.10.12/unlock_device.php?action=check&hostname=pc1"
```

### Lock Device

**By MAC address:**
```bash
curl "http://192.168.10.12/unlock_device.php?action=lock&mac=00:11:22:33:44:55"
```

**By hostname:**
```bash
curl "http://192.168.10.12/unlock_device.php?action=lock&hostname=pc1"
```

### Unlock All Devices
```bash
curl "http://192.168.10.12/unlock_device.php?action=unlock-all"
```

### Web Interface
```
http://192.168.10.12/control_panel.php
```

## Complete Workflow

### Step 1: Unlock Device
```bash
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
```

**Response:**
```json
{
  "status": "success",
  "message": "Device unlocked",
  "mac": "00:11:22:33:44:55",
  "timeout_seconds": 300
}
```

### Step 2: Device Boots (or Set to PXE Boot)
Device will now successfully PXE boot when it tries.

### Step 3: Installation Begins
Normal RHEL kickstart installation proceeds.

### Step 4: Lock is Consumed
Lock is automatically removed after successful boot.

## What Happens When Locked?

If device tries to PXE boot while locked, it sees:

```
Device is LOCKED for PXE boot
MAC: 00:11:22:33:44:55
========================================
This device requires explicit unlock
Unlock via: curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
or visit: http://192.168.10.12/control_panel.php
========================================
```

Then exits without proceeding to installation.

## Features

✅ **Locked by Default** - No unauthorized boots  
✅ **curl Control** - Remote unlock/lock  
✅ **Web Interface** - Visual control panel  
✅ **Time-Limited** - 5 minutes default (configurable)  
✅ **One-Time Use** - Auto-locks after boot  
✅ **Audit Trail** - All attempts logged to `boot.log`

## Directory Structure

```
PXEBoot/
├── boot.php                 # ✅ Modified - checks unlock status
├── unlock_device.php        # ✨ NEW - unlock API
├── control_panel.php        # ✨ NEW - web interface  
├── devices.ini              # Device configuration
├── locks/                   # ✨ NEW - lock files (auto-created)
│   └── *.lock               # Individual lock files
├── boot.log                 # Boot attempt logs
└── UNLOCK_GUIDE.md          # ✨ NEW - complete docs
```

## Lock File Format

Lock files are stored in `locks/` directory:
```
locks/00-11-22-33-44-55.lock
```

Contains expiration timestamp:
```
1704523456
```

## API Reference

### Unlock Device
```bash
GET /unlock_device.php?action=unlock&mac=XX:XX:XX:XX:XX:XX&timeout=300
```
- `timeout`: Optional, in seconds (default: 300)

### Lock Device
```bash
GET /unlock_device.php?action=lock&mac=XX:XX:XX:XX:XX:XX
```

### Check Status
```bash
GET /unlock_device.php?action=check&mac=XX:XX:XX:XX:XX:XX
```

### Unlock All
```bash
GET /unlock_device.php?action=unlock-all
```

## Example Scripts

### Bash Deployment Script
```bash
#!/bin/bash
MAC="00:11:22:33:44:55"
SERVER="192.168.10.12"

echo "Unlocking device..."
curl "http://$SERVER/unlock_device.php?action=unlock&mac=$MAC"

echo "Device will boot into PXE on next restart."
```

### PowerShell Script (Windows)
```powershell
$mac = "00:11:22:33:44:55"
$server = "192.168.10.12"

Invoke-WebRequest -Uri "http://$server/unlock_device.php?action=unlock&mac=$mac"
```

### Python Script
```python
import requests

def unlock_device(mac):
    url = "http://192.168.10.12/unlock_device.php"
    params = {'action': 'unlock', 'mac': mac}
    return requests.get(url, params=params).json()

result = unlock_device('00:11:22:33:44:55')
print(result)
```

## Testing

### Test with VM
1. Start a VM
2. Keep it locked
3. Try to PXE boot - should see locked message
4. Unlock via curl
5. Try PXE boot again - should proceed

### Test Lock Expiration
1. Unlock device
2. Wait 5 minutes
3. Try to boot - should be locked again

### Test One-Time Use
1. Unlock device
2. Boot device successfully
3. Try to boot again - should be locked
4. Need to unlock again

## Security Benefits

🔒 **Prevent Accidental Installations**
- Devices won't boot even if PXE is enabled in BIOS
- Requires explicit authorization

⏱️ **Time-Limited Access**
- Locks expire automatically
- Reduces exposure window

📝 **Audit Trail**
- All attempts logged
- Track who/what attempted boots
- Monitor for unauthorized attempts

🎯 **Granular Control**
- Lock/unlock individual devices
- Unlock all at once for batch deployments
- Lock specific devices from booting

## Troubleshooting

### Lock Directory Not Created
```bash
# Create manually if needed
mkdir -p locks
chmod 755 locks
```

### Permissions Issue
```bash
# Ensure web server can write
chown -R apache:apache locks/
```

### Lock Not Working
- Check `boot.log` for errors
- Verify PHP is working
- Check file permissions
- Review web server logs

## Next Steps

1. ✅ Test the unlock system
2. ⚠️ Add authentication for production
3. ⚠️ Configure HTTPS
4. ⚠️ Set up monitoring
5. ⚠️ Create deployment automation

## Documentation Files

- `UNLOCK_GUIDE.md` - Complete unlock system documentation
- `README.md` - Main documentation
- `QUICK_START.md` - Quick reference
- `IMPROVEMENTS.md` - Previous improvements list
- `SUMMARY.md` - This file

## Previous Improvements

Your system also includes:
- ✅ Enhanced `boot.php` with logging and validation
- ✅ Fixed DHCP configuration
- ✅ Created missing kickstart files
- ✅ Comprehensive documentation
- ✅ Helper scripts (`validate.sh`, `add_device.sh`, `view_logs.sh`)

See `IMPROVEMENTS.md` for details.
