#!/bin/bash
#
# Validate PXE boot configuration
#

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=== Validating PXE Boot Configuration ==="
echo

ERRORS=0
WARNINGS=0

# Check if boot.php exists
if [ -f "boot.php" ]; then
    echo -e "${GREEN}✓ boot.php exists${NC}"
else
    echo -e "${RED}✗ boot.php not found${NC}"
    ((ERRORS++))
fi

# Check if devices.ini exists
if [ -f "devices.ini" ]; then
    echo -e "${GREEN}✓ devices.ini exists${NC}"
else
    echo -e "${RED}✗ devices.ini not found${NC}"
    ((ERRORS++))
fi

# Check if Kickstart directory exists
if [ -d "Kickstart" ]; then
    echo -e "${GREEN}✓ Kickstart directory exists${NC}"
else
    echo -e "${RED}✗ Kickstart directory not found${NC}"
    ((ERRORS++))
fi

# Check if dhcp configuration exists
if [ -f "dhcp/dhcpd.conf" ]; then
    echo -e "${GREEN}✓ DHCP configuration exists${NC}"
else
    echo -e "${RED}✗ DHCP configuration not found${NC}"
    ((ERRORS++))
fi

# Validate kickstart files
echo
echo "Validating kickstart files:"

# Parse devices.ini to find referenced kickstart files
grep -E "^kickstart\s*=" devices.ini 2>/dev/null | while read -r line; do
    kickstart_file=$(echo "$line" | cut -d'=' -f2 | tr -d ' ')
    if [ -n "$kickstart_file" ]; then
        if [ -f "Kickstart/$kickstart_file" ]; then
            echo -e "${GREEN}✓ $kickstart_file${NC}"
            
            # Check if ksvalidator is available
            if command -v ksvalidator &> /dev/null; then
                if ksvalidator "Kickstart/$kickstart_file" 2>/dev/null; then
                    echo -e "  ${GREEN}  Valid syntax${NC}"
                else
                    echo -e "  ${YELLOW}  Syntax warnings (run ksvalidator for details)${NC}"
                    ((WARNINGS++))
                fi
            fi
        else
            echo -e "${YELLOW}⚠ Referenced kickstart file not found: $kickstart_file${NC}"
            ((WARNINGS++))
        fi
    fi
done

# Check for RHEL files
echo
echo "Checking RHEL installation files:"

if [ -f "rhel/vmlinuz" ]; then
    echo -e "${GREEN}✓ vmlinuz found${NC}"
else
    echo -e "${YELLOW}⚠ vmlinuz not found in rhel/ directory${NC}"
    ((WARNINGS++))
fi

if [ -f "rhel/initrd.img" ]; then
    echo -e "${GREEN}✓ initrd.img found${NC}"
else
    echo -e "${YELLOW}⚠ initrd.img not found in rhel/ directory${NC}"
    ((WARNINGS++))
fi

# Validate PHP syntax
if command -v php &> /dev/null && [ -f "boot.php" ]; then
    echo
    echo "Validating PHP syntax:"
    if php -l boot.php &> /dev/null; then
        echo -e "${GREEN}✓ boot.php has valid PHP syntax${NC}"
    else
        echo -e "${RED}✗ boot.php has syntax errors${NC}"
        php -l boot.php
        ((ERRORS++))
    fi
fi

# Check for IP conflicts in dhcpd.conf
if [ -f "dhcp/dhcpd.conf" ]; then
    echo
    echo "Checking DHCP configuration:"
    
    # Check for IP conflicts
    ip_count=$(grep -c "fixed-address" dhcp/dhcpd.conf || echo "0")
    if [ "$ip_count" -gt 0 ]; then
        # Extract IPs and check for duplicates
        duplicated_ips=$(grep "fixed-address" dhcp/dhcpd.conf | awk '{print $2}' | sort | uniq -d)
        if [ -n "$duplicated_ips" ]; then
            echo -e "${RED}✗ Found duplicate IP addresses in DHCP config:${NC}"
            echo "$duplicated_ips"
            ((ERRORS++))
        else
            echo -e "${GREEN}✓ No IP address conflicts found${NC}"
        fi
    fi
fi

# Summary
echo
echo "=== Summary ==="
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}Configuration is valid!${NC}"
    exit 0
elif [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Found $ERRORS error(s) and $WARNINGS warning(s)${NC}"
    exit 1
else
    echo -e "${YELLOW}Found $WARNINGS warning(s)${NC}"
    exit 0
fi
