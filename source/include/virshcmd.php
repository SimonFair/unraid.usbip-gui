<?PHP
/* 
 *  Execute Virsh Command
 */

$action = $_POST['action'];
$vmname = $_POST['VMNAME'];
$usbbus = $_POST['BUS'];
$usbdev = $_POST['DEV'];
$usbstr = '';
if (!empty($usbid)) 
{
	
	$usbstr .= "<hostdev mode='subsystem' type='usb'>
<source>
<address bus=".$usbdev." device=".$usbdev." />
</source>
</hostdev>";
}
file_put_contents('/tmp/libvirthotplugusbbybus.xml',$usbstr);

echo "\n".shell_exec("/usr/sbin/virsh $action-device '$vmname' /tmp/libvirthotplugusb.xml 2>&1");


#echo "Running virsh ${COMMAND} ${DOMAIN} for USB bus=${BUSNUM} device=${DEVNUM}:" >&2
#virsh "${COMMAND}" "${DOMAIN}" /dev/stdin <<END
#<hostdev mode='subsystem' type='usb'>
#  <source>
#    <address bus='${BUSNUM}' device='${DEVNUM}' />
#  </source>
#</hostdev>
#END
?>