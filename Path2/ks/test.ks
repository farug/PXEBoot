#version=RHEL8
text
skipx

lang en_US.UTF-8
keyboard us
timezone Europe/Istanbul --isUtc

repo --name="BaseOS" --baseurl="http://192.168.10.10/content/BaseOS"
repo --name="AppStream" --baseurl="http://192.168.10.10/content/AppStream"

network --bootproto=dhcp --device=link --activate --onboot=on --hostname=test-a

rootpw --plaintext test123
user --name=maintain --groups=wheel --password=test123 --plaintext

auth --enableshadow --passalgo=sha512
selinux --enforcing
firewall --enabled --service=ssh --port=3389:tcp

services --enabled=sshd,NetworkManager,xrdp

bootloader --location=mbr --boot-drive=sda
reboot

ignoredisk --only-use=sda
zerombr
clearpart --all --initlabel --drives=sda

part /boot --fstype=xfs --size=1024 --ondisk=sda --asprimary

part pv.01 --fstype=lvmpv --size=1 --grow --ondisk=sda --encrypted --passphrase=test123

volgroup vg0 pv.01

logvol /     --vgname=vg0 --name=root --fstype=xfs  --size=102400
logvol /home --vgname=vg0 --name=home --fstype=xfs  --size=1 --grow
logvol swap  --vgname=vg0 --name=swap --fstype=swap --size=4096

%packages
@^graphical-server-environment
iptables-services
xrdp
%end

%post
systemctl enable sshd
systemctl enable iptables
systemctl enable xrdp
systemctl set-default graphical.target

echo "Kickstart with encrypted LVM, GNOME, and xrdp is complete." > /root/ks-done.txt
%end
