# PXE Boot Server for RHEL Installations

A complete PXE boot infrastructure for automated RHEL (Red Hat Enterprise Linux) installations using iPXE and kickstart files.

## Features

- **Dynamic iPXE boot scripts** based on MAC address
- **Automated RHEL installation** using kickstart files
- **Per-device configuration** via INI file
- **Boot attempt logging** for troubleshooting
- **IP address allocation** with DHCP reservations
- **Flexible architecture** supporting multiple deployment scenarios

## Architecture

```
┌─────────────┐
│   Client    │
│  (PXE Boot) │
└──────┬──────┘
       │
       ↓
┌─────────────┐         ┌──────────────┐
│   DHCP      │────────→│boot.php      │
│   Server    │  MAC    │(iPXE Script)  │
└─────────────┘         └──────┬───────┘
                                │
                                ↓
                    ┌──────────────────────────┐
                    │  RHEL Kickstart Install  │
                    │  - Kernel (vmlinuz)      │
                    │  - Initrd (initrd.img)   │
                    │  - Kickstart (.ks)       │
                    └──────────────────────────┘
```

## Components

### 1. DHCP Configuration (`dhcp/dhcpd.conf`)
- Configures DHCP server for PXE booting
- Defines fixed IP addresses based on MAC
- Points clients to the iPXE boot script

### 2. Boot Script (`boot.php`)
- Dynamically generates iPXE scripts based on MAC address
- Validates MAC address format
- Loads device configuration from `devices.ini`
- Logs all boot attempts
- Provides error handling and security

### 3. Device Configuration (`devices.ini`)
- Maps MAC addresses to hostnames
- Associates devices with kickstart files
- Easy to add/remove devices

### 4. Kickstart Files (`Kickstart/`)
- Automated installation scripts
- Configure partitioning, packages, and post-install tasks
- Separate files for different deployment scenarios

## Directory Structure

```
PXEBoot/
│
├── boot.php              # Dynamic iPXE boot script generator
├── devices.ini           # Device configuration (MAC → hostname/kickstart)
├── boot.log              # Boot attempt logs (auto-generated)
│
├── dhcp/
│   └── dhcpd.conf        # DHCP server configuration
│
├── Kickstart/
│   ├── pc1.ks           # Server kickstart (minimal install)
│   ├── pc2.ks           # Desktop kickstart (GNOME)
│   └── test.ks          # Test kickstart (encrypted LVM)
│
├── ipxe/                 # iPXE boot files directory
│
├── rhel/                 # RHEL installation files
│   ├── vmlinuz          # Kernel
│   └── initrd.img       # Initial ramdisk
│
└── README.md            # This file
```

## Prerequisites

### Server Requirements
- **Operating System**: RHEL 8+ or CentOS 8+
- **PHP**: 7.4+ with Apache/Nginx
- **DHCP Server**: `dhcpd` (isc-dhcp-server)
- **Web Server**: Apache or Nginx
- **PXE/TFTP Server** (optional, if not using HTTP)

### RHEL Installation Files
You need to extract the following from a RHEL ISO:
- `vmlinuz` (kernel)
- `initrd.img` (initial ramdisk)

Place them in the `rhel/` directory.

## Installation

### 1. Install Required Packages

```bash
# RHEL/CentOS
sudo dnf install -y httpd php dhcp
```

### 2. Extract RHEL Files

```bash
# Mount RHEL ISO
sudo mount -o loop RHEL-8.x-x86_64-dvd.iso /mnt

# Create rhel directory
mkdir -p /var/www/html/rhel

# Copy kernel and initrd
cp /mnt/images/pxeboot/vmlinuz /var/www/html/rhel/
cp /mnt/images/pxeboot/initrd.img /var/www/html/rhel/

# Unmount
sudo umount /mnt
```

### 3. Configure Web Server

#### For Apache:

```bash
# Copy PHP file
sudo cp boot.php /var/www/html/

# Copy kickstart files
sudo cp -r Kickstart/ /var/www/html/kickstarts/

# Configure permissions
sudo chown -R apache:apache /var/www/html
sudo chmod 755 /var/www/html/boot.php
sudo chmod 644 /var/www/html/devices.ini
```

#### For Nginx:

```nginx
server {
    listen 80;
    root /var/www/html;
    index boot.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/www.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### 4. Configure DHCP Server

```bash
# Copy DHCP configuration
sudo cp dhcp/dhcpd.conf /etc/dhcp/dhcpd.conf

# Edit configuration with your network settings
sudo vi /etc/dhcp/dhcpd.conf

# Start and enable DHCP
sudo systemctl enable dhcpd
sudo systemctl start dhcpd
```

**Important**: Modify the following in `dhcpd.conf`:
- Subnet address (`192.168.10.0`)
- Network mask
- Gateway IP (`next-server`)
- IP ranges and fixed addresses

### 5. Create iPXE ROM (if needed)

If you need to provide iPXE ROM via TFTP:

```bash
# Download and compile iPXE
git clone http://git.ipxe.org/ipxe.git
cd ipxe/src
make bin/undionly.kpxe

# Copy to TFTP directory
sudo cp bin/undionly.kpxe /var/lib/tftpboot/
```

## Configuration

### Adding New Devices

Edit `devices.ini`:

```ini
[AA:BB:CC:DD:EE:FF]
hostname = newserver
kickstart = servers.ks
```

Then update `dhcp/dhcpd.conf`:

```conf
host newserver {
    hardware ethernet AA:BB:CC:DD:EE:FF;
    fixed-address 192.168.10.103;
    filename "http://192.168.10.1/boot.php?mac=${net0/mac}";
    option host-name "newserver";
}
```

### Creating Kickstart Files

1. Start with an existing template (`Kickstart/pc1.ks` or `pc2.ks`)
2. Customize for your needs:
   - Partitioning scheme
   - Package selection
   - Post-install scripts
   - Security hardening

3. Test your kickstart file:

```bash
ksvalidator pc1.ks
```

## Usage

### Client Boot Process

1. Client boots from network (PXE)
2. DHCP assigns IP and provides boot file
3. Client loads iPXE ROM
4. iPXE calls `boot.php?mac=XX:XX:XX:XX:XX:XX`
5. `boot.php` returns iPXE script based on MAC
6. Client loads kernel and initrd
7. Anaconda starts with kickstart file
8. Installation completes automatically

### Logging

Check boot attempts:

```bash
tail -f boot.log
```

Example output:
```
[2024-01-15 10:23:45] MAC: 00:11:22:33:44:55 | Hostname: pc1 | Status: SUCCESS | IP: 192.168.10.101
```

### Troubleshooting

#### Client not getting IP
- Check DHCP server is running: `systemctl status dhcpd`
- Verify network interface is configured
- Check firewall rules

#### Invalid kickstart file
- Validate: `ksvalidator Kickstart/pc1.ks`
- Check syntax errors
- Verify file paths

#### Boot fails at iPXE
- Check web server is accessible
- Verify PHP is working: `php -v`
- Review `boot.log` for error messages

#### Installation hangs
- Check kernel parameters in `boot.php`
- Verify RHEL files are accessible
- Review Anaconda logs

## Security Considerations

### Current Implementation
- ✅ MAC address validation
- ✅ Input sanitization
- ✅ Error logging
- ✅ Kickstart file validation

### Recommendations

1. **Change default passwords** in kickstart files
2. **Use HTTPS** for production deployments
3. **Implement MAC address whitelist**
4. **Secure devices.ini** with proper file permissions (600)
5. **Enable firewall** rules for DHCP/UDP ports
6. **Add authentication** for sensitive operations
7. **Regularly rotate** root passwords
8. **Monitor boot.log** for unauthorized access attempts

### Example Security Hardening

```ini
# devices.ini should have restricted permissions
chmod 600 devices.ini
chown root:root devices.ini
```

```bash
# Configure firewall
sudo firewall-cmd --permanent --add-service=dhcp
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --reload
```

## Advanced Features

### Custom iPXE Menus

Modify `boot.php` to add interactive menus:

```php
echo <<<IPXE
#!ipxe
menu Boot Options
item normal   Normal Install
item rescue   Rescue Mode
choose --default normal option
isset \${option} && goto \${option} || goto failed
:normal
kernel {$kernelUrl} inst.ks={$kickstartUrl}
initrd {$initrdUrl}
boot
IPXE;
```

### Network Boot Chain

For clients without iPXE support:

```
PXE → GRUB2 → iPXE → boot.php → RHEL Install
```

### Encrypted Installations

See `test.ks` for an example of encrypted LVM setup with full disk encryption.

## Contributing

Feel free to submit issues and enhancement requests!

## License

This project is provided as-is for educational and production use.

## Support

For issues or questions:
1. Check `boot.log` for errors
2. Review DHCP server logs: `journalctl -u dhcpd`
3. Verify network connectivity and firewall rules
4. Test kickstart files manually

## References

- [iPXE Documentation](http://ipxe.org/start)
- [RHEL Kickstart Guide](https://access.redhat.com/documentation/en-us/red_hat_enterprise_linux/8/html/performing_an_advanced_rhel_8_installation/assembly_kickstart-files#)
- [ISC DHCP Documentation](https://kb.isc.org/docs/isc-dhcp-45-manual-pages-dhcpdconf)
