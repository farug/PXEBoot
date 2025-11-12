# Unlocking Devices by Hostname

You can now unlock/lock devices using either **MAC address** or **hostname**. Hostname is much easier to remember!

## Examples

### Unlock Device
```bash
# By hostname (easier!)
curl "http://192.168.10.12/unlock_device.php?action=unlock&hostname=pc1"

# By MAC address (also works)
curl "http://192.168.10.12/unlock_device.php?action=unlock&mac=00:11:22:33:44:55"
```

### Lock Device
```bash
curl "http://192.168.10.12/unlock_device.php?action=lock&hostname=pc1"
```

### Check Status
```bash
curl "http://192.168.10.12/unlock_device.php?action=check&hostname=pc1"
```

**Response includes both MAC and hostname:**
```json
{
  "status": "success",
  "mac": "00:11:22:33:44:55",
  "hostname": "pc1",
  "unlocked": true,
  "time_remaining": 240,
  "time_remaining_formatted": "00:04:00"
}
```

## Why Use Hostname?

✅ **Easier to Remember**
- `hostname=pc1` is easier than `mac=00:11:22:33:44:55`

✅ **More Readable Scripts**
```bash
# Clear and readable
curl "...&hostname=web-server-01"

# vs hard to remember
curl "...&mac=00:11:22:33:44:55"
```

✅ **Better Documentation**
- Scripts are self-documenting
- No need to look up MAC addresses

## Real-World Examples

### Deploy to Specific Server
```bash
#!/bin/bash
# Deploy to web server

SERVER_NAME="web-server-01"
PXE_SERVER="192.168.10.12"

echo "Unlocking $SERVER_NAME for PXE boot..."
curl -s "http://$PXE_SERVER/unlock_device.php?action=unlock&hostname=$SERVER_NAME"

echo "Unlocked! Restart the server to begin installation."
```

### Check Multiple Servers Status
```bash
#!/bin/bash
# Check status of multiple servers

for hostname in pc1 pc2 web-server-01 db-server-01; do
    echo -n "$hostname: "
    curl -s "http://192.168.10.12/unlock_device.php?action=check&hostname=$hostname" \
        | jq -r '.unlocked | if . then "UNLOCKED" else "LOCKED" end'
done
```

Output:
```
pc1: UNLOCKED
pc2: LOCKED
web-server-01: LOCKED
db-server-01: UNLOCKED
```

### Unlock Batch by Hostname Pattern
```bash
#!/bin/bash
# Unlock all web servers

for hostname in web-server-01 web-server-02 web-server-03; do
    echo "Unlocking $hostname..."
    curl -s "http://192.168.10.12/unlock_device.php?action=unlock&hostname=$hostname"
done

echo "All web servers unlocked!"
```

## PowerShell Examples (Windows)

### Unlock by Hostname
```powershell
$hostname = "pc1"
$server = "192.168.10.12"

Invoke-WebRequest -Uri "http://$server/unlock_device.php?action=unlock&hostname=$hostname"
```

### Check Status with Pretty Output
```powershell
$hostname = "web-server-01"
$response = Invoke-WebRequest -Uri "http://192.168.10.12/unlock_device.php?action=check&hostname=$hostname"
$json = $response.Content | ConvertFrom-Json

Write-Host "Hostname: $($json.hostname)"
Write-Host "MAC: $($json.mac)"
Write-Host "Unlocked: $($json.unlocked)"
Write-Host "Time Remaining: $($json.time_remaining_formatted)"
```

## Python Examples

### Unlock Device
```python
import requests

def unlock_by_hostname(server_ip, hostname, timeout=300):
    url = f"http://{server_ip}/unlock_device.php"
    params = {
        'action': 'unlock',
        'hostname': hostname,
        'timeout': timeout
    }
    response = requests.get(url, params=params)
    return response.json()

# Usage
result = unlock_by_hostname('192.168.10.12', 'pc1')
print(f"Unlocked: {result['hostname']} ({result['mac']})")
```

### Check All Servers
```python
import requests

devices = ['pc1', 'pc2', 'web-server-01']
server = '192.168.10.12'

for hostname in devices:
    url = f"http://{server}/unlock_device.php"
    response = requests.get(url, params={'action': 'check', 'hostname': hostname})
    data = response.json()
    
    status = "UNLOCKED" if data['unlocked'] else "LOCKED"
    print(f"{hostname}: {status} ({data['time_remaining_formatted']})")
```

## Error Handling

### Invalid Hostname
```bash
curl "http://192.168.10.12/unlock_device.php?action=check&hostname=nonexistent"
```

**Response:**
```json
{
  "status": "error",
  "message": "Hostname not found: nonexistent"
}
```

### Missing Both Parameters
```bash
curl "http://192.168.10.12/unlock_device.php?action=check"
```

**Response:**
```json
{
  "status": "error",
  "message": "Either MAC address or hostname is required"
}
```

## Tips

1. **Use hostname for human-readable scripts**
2. **Use MAC address for automated systems**
3. **Both methods work identically - choose based on your needs**
4. **Response includes both MAC and hostname for reference**
5. **Hostname is case-insensitive**

## Summary

| Action | By Hostname | By MAC Address |
|--------|-------------|----------------|
| Unlock | `hostname=pc1` | `mac=00:11:22:33:44:55` |
| Lock | `hostname=pc1` | `mac=00:11:22:33:44:55` |
| Check | `hostname=pc1` | `mac=00:11:22:33:44:55` |

Both methods work exactly the same - use whichever is more convenient for your use case!
