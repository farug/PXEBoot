#!/bin/bash
#
# Script to add a new device to the PXE boot system
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=== Add Device to PXE Boot System ==="
echo

# Get MAC address
read -p "Enter MAC address (format: XX:XX:XX:XX:XX:XX): " mac
if [[ ! "$mac" =~ ^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$ ]]; then
    echo -e "${RED}Error: Invalid MAC address format${NC}"
    exit 1
fi

# Get hostname
read -p "Enter hostname: " hostname
if [ -z "$hostname" ]; then
    echo -e "${RED}Error: Hostname cannot be empty${NC}"
    exit 1
fi

# Get kickstart file
read -p "Enter kickstart filename (e.g., pc1.ks): " kickstart
if [ -z "$kickstart" ]; then
    echo -e "${RED}Error: Kickstart filename cannot be empty${NC}"
    exit 1
fi

# Check if kickstart file exists
if [ ! -f "Kickstart/$kickstart" ]; then
    echo -e "${YELLOW}Warning: Kickstart file 'Kickstart/$kickstart' not found${NC}"
    read -p "Continue anyway? (y/n): " continue_anyway
    if [ "$continue_anyway" != "y" ]; then
        exit 1
    fi
fi

# Get IP address
read -p "Enter IP address (e.g., 192.168.10.103): " ipaddress
if [ -z "$ipaddress" ]; then
    echo -e "${RED}Error: IP address cannot be empty${NC}"
    exit 1
fi

# Convert MAC to dash format
mac_dash=$(echo "$mac" | tr '[:upper:]' '[:lower:]' | tr ':' '-')

# Add to devices.ini
echo "" >> devices.ini
echo "[$mac_dash]" >> devices.ini
echo "hostname = $hostname" >> devices.ini
echo "kickstart = $kickstart" >> devices.ini

echo
echo -e "${GREEN}✓ Device added to devices.ini${NC}"

# Generate DHCP entry
dhcp_entry="
host $hostname {
    hardware ethernet $mac;
    fixed-address $ipaddress;
    filename \"http://192.168.10.1/boot.php?mac=\${net0/mac}\";
    option host-name \"$hostname\";
}"

echo
echo "Add this to /etc/dhcp/dhcpd.conf:"
echo
echo "$dhcp_entry"

echo
read -p "Copy DHCP entry to clipboard? (y/n): " copy_clipboard
if [ "$copy_clipboard" = "y" ]; then
    echo "$dhcp_entry" | xclip -selection clipboard 2>/dev/null || echo "$dhcp_entry" | clip 2>/dev/null || echo "Could not access clipboard"
    echo -e "${GREEN}✓ Copied to clipboard${NC}"
fi

echo
echo -e "${GREEN}✓ Device configuration complete!${NC}"
echo
echo "Summary:"
echo "  MAC: $mac"
echo "  Hostname: $hostname"
echo "  Kickstart: $kickstart"
echo "  IP: $ipaddress"
echo
echo "Next steps:"
echo "1. Edit /etc/dhcp/dhcpd.conf and add the DHCP entry"
echo "2. Restart DHCP server: sudo systemctl restart dhcpd"
echo "3. Test PXE boot from the client"
