# Kickstart configuration for pc2
# Desktop installation with GNOME
#version=RHEL8

# Localization
lang en_US.UTF-8
keyboard us
timezone America/New_York --isUtc

# Network configuration
network --bootproto=dhcp --device=eth0 --onboot=on --hostname=pc2

# Users
rootpw --plaintext ChangeMe123!
user --name=admin --groups=wheel --password=ChangeMe123! --plaintext --gecos="System Administrator"

# System authorization
auth --enableshadow --passalgo=sha512

# SELinux and Firewall
selinux --enforcing
firewall --enabled --service=ssh,vnc-server,3389

# System services
services --enabled=sshd,network

# Bootloader
bootloader --location=mbr --boot-drive=sda

# Partitioning - standard LVM setup
ignoredisk --only-use=sda
zerombr
clearpart --all --initlabel --drives=sda

part /boot --fstype="xfs" --size=1024
part pv.01 --size=1 --grow
volgroup vg0 pv.01
logvol / --vgname=vg0 --name=root --fstype="xfs" --size=40960
logvol /home --vgname=vg0 --name=home --fstype="xfs" --size=20480
logvol swap --vgname=vg0 --name=swap --fstype="swap" --size=4096

# Package selection - GNOME desktop
%packages
@^graphical-server-environment
@gnome-desktop
@gnome-apps
@internet-browser
firefox
vim
%end

# Post-installation
%post
systemctl set-default graphical.target
# Copy authorized keys if available
mkdir -p /root/.ssh
chmod 700 /root/.ssh
echo "Desktop installation complete." > /root/ks-done.txt
%end

# Reboot after installation
reboot
