#version=RHEL8
lang en_US.UTF-8
keyboard us
timezone Europe/Istanbul --isUtc

network --bootproto=dhcp --device=eth0 --onboot=on --hostname=test-a

# Users
rootpw --plaintext test123
user --name=maintain --groups=wheel --password=test123 --plaintext

auth --enableshadow --passalgo=sha512
selinux --enforcing
firewall --enabled --service=ssh

services --enabled=sshd,network

bootloader --location=mbr --boot-drive=sda
reboot

# Disk setup: Full disk encryption with manual LVM
ignoredisk --only-use=sda
zerombr
clearpart --all --initlabel --drives=sda

# Create boot partition
part /boot --fstype="xfs" --size=1024 --asprimary

# Encrypted partition for LVM
part pv.01 --size=1 --grow --ondisk=sda
pvcreate --encrypted --passphrase=test123 pv.01

# Create LVM volume group and LVs
volgroup vg0 pv.01
logvol / --vgname=vg0 --name=root --fstype="xfs" --size=102400
logvol /home --vgname=vg0 --name=home --fstype="xfs" --size=1 --grow
logvol swap --vgname=vg0 --name=swap --fstype="swap" --size=4096

# Package selection
%packages
@^graphical-server-environment
@gnome-desktop
iptables-services
xrdp
%end

# Post-install configuration
%post
systemctl enable iptables
systemctl enable xrdp
firewall-cmd --permanent --add-port=3389/tcp
firewall-cmd --reload
echo "Kickstart with encrypted LVM, GNOME, and xrdp is complete." > /root/ks-done.txt
%end
