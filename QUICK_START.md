# Quick Start Guide - PXE Boot Server

## 🚀 Quick Setup (5 minutes)

### 1. Prepare RHEL Files
```bash
# Mount RHEL ISO and extract files
sudo mount -o loop RHEL-8.x-x86_64-dvd.iso /mnt
mkdir -p rhel
cp /mnt/images/pxeboot/vmlinuz rhel/
cp /mnt/images/pxeboot/initrd.img rhel/
sudo umount /mnt
```

### 2. Install Required Packages
```bash
sudo dnf install -y httpd php dhcp
```

### 3. Copy Files
```bash
sudo cp boot.php /var/www/html/
sudo cp devices.ini /var/www/html/
sudo cp -r Kickstart/ /var/www/html/kickstarts/
sudo cp -r rhel/ /var/www/html/
sudo chown -R apache:apache /var/www/html
```

### 4. Configure DHCP
```bash
# Edit IP addresses in dhcpd.conf first!
sudo cp dhcp/dhcpd.conf /etc/dhcp/dhcpd.conf
sudo vi /etc/dhcp/dhcpd.conf
sudo systemctl enable --now dhcpd
```

### 5. Start Web Server
```bash
sudo systemctl enable --now httpd
```

## 📝 Common Commands

### Add a New Device
```bash
./add_device.sh
```

### View Boot Logs
```bash
./view_logs.sh tail      # Live log viewing
./view_logs.sh today     # Today's logs
./view_logs.sh errors    # Only errors
```

### Validate Configuration
```bash
./validate.sh
```

### Check Status
```bash
sudo systemctl status dhcpd
sudo systemctl status httpd
tail -f boot.log
```

## 🔧 Configuration Files

| File | Purpose |
|------|---------|
| `devices.ini` | MAC address → hostname/kickstart mapping |
| `dhcp/dhcpd.conf` | DHCP server configuration |
| `boot.php` | Dynamic iPXE script generator |
| `Kickstart/*.ks` | Kickstart installation files |

## 🌐 Client Boot URL Format

```
http://YOUR_SERVER_IP/boot.php?mac=XX:XX:XX:XX:XX:XX
```

iPXE will call this automatically after DHCP.

## ⚠️ Important Notes

1. **Update IP addresses** in `dhcp/dhcpd.conf` to match your network
2. **Change default passwords** in kickstart files before production use
3. **Test with a VM first** before deploying to physical hardware
4. **Check firewall** rules for DHCP/UDP and HTTP/TCP

## 📚 Full Documentation

See `README.md` for complete documentation.

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| Client not getting IP | Check DHCP: `systemctl status dhcpd` |
| Boot fails | Check logs: `tail -f boot.log` |
| Invalid kickstart | Run: `ksvalidator Kickstart/pc1.ks` |
| PHP errors | Check syntax: `php -l boot.php` |

## 🔒 Security Checklist

- [ ] Change default passwords in kickstart files
- [ ] Set `devices.ini` permissions: `chmod 600`
- [ ] Configure firewall rules
- [ ] Update IP addresses for your network
- [ ] Enable HTTPS for production
- [ ] Monitor boot.log for unauthorized access
