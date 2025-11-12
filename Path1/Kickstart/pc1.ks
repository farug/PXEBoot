# Kickstart configuration for pc1
# Minimal server installation
#version=RHEL8

# Localization
lang en_US.UTF-8
keyboard us
timezone America/New_York --isUtc

# Network configuration
network --bootproto=dhcp --device=eth0 --onboot=on --hostname=pc1

# Root password (CHANGE THIS IN PRODUCTION!)
rootpw --plaintext ChangeMe123!

# System authorization
auth --enableshadow --passalgo=sha512

# SELinux and Firewall
selinux --enforcing
firewall --enabled --service=ssh

# System services
services --enabled=sshd,network

# Bootloader
bootloader --location=mbr --boot-drive=sda

# Partitioning - standard LVM setup
ignoredisk --only-use=sda
zerombr
clearpart --all --initlabel --drives=sda

# Create physical volume
part pv.01 --size=1 --grow --ondisk=sda

# Create volume group
volgroup vg0 pv.01

# Create logical volumes
logvol / --vgname=vg0 --name=root --fstype="xfs" --size=20480
logvol /home --vgname=vg0 --name=home --fstype="xfs" --size=10240
logvol swap --vgname=vg0 --name=swap --fstype="swap" --size=2048

# Install minimal server packages
%packages
@^minimal
@core
vim
curl
wget
%end

# Post-installation
%post
# Disable root SSH password login for security
sed -i 's/#PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
echo "Server installation complete." > /root/ks-done.txt
%end

# Reboot after installation
reboot
