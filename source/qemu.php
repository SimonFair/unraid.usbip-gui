#!/usr/bin/env php

<?php

if ($argv[2] == 'prepare' || $argv[2] == 'stopped'){
    shell_exec("/usr/local/emhttp/plugins/unraid.usbip-gui/scripts/rc.unraid.usbip-gui vm_action {$argv[1]} {$argv[2]} {$argv[3]} {$argv[4]}  >/dev/null 2>&1 & disown") ;
}

if (!isset($argv[2]) || $argv[2] != 'start') {
        exit(0);
}

$strXML = file_get_contents('php://stdin');

$doc = new DOMDocument();
$doc->loadXML($strXML);

$xpath = new DOMXpath($doc);

$args = $xpath->evaluate("//domain/*[name()='qemu:commandline']/*[name()='qemu:arg']/@value");

for ($i = 0; $i < $args->length; $i++){
        $arg_list = explode(',', $args->item($i)->nodeValue);

        if ($arg_list[0] !== 'vfio-pci') {
                continue;
        }

        foreach ($arg_list as $arg) {
                $keypair = explode('=', $arg);

                if ($keypair[0] == 'host' && !empty($keypair[1])) {
                        vfio_bind($keypair[1]);
                        break;
                }
        }
}

exit(0); // end of script



function vfio_bind($strPassthruDevice) {
        // Ensure we have leading 0000:
        $strPassthruDeviceShort = str_replace('0000:', '', $strPassthruDevice);
        $strPassthruDeviceLong = '0000:' . $strPassthruDeviceShort;

        // Determine the driver currently assigned to the device
        $strDriverSymlink = @readlink('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver');

        if ($strDriverSymlink !== false) {
                // Device is bound to a Driver already

                if (strpos($strDriverSymlink, 'vfio-pci') !== false) {
                        // Driver bound to vfio-pci already - nothing left to do for this device now regarding vfio
                        return true;
                }

                // Driver bound to some other driver - attempt to unbind driver
                if (file_put_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver/unbind', $strPassthruDeviceLong) === false) {
                        file_put_contents('php://stderr', 'Failed to unbind device ' . $strPassthruDeviceShort . ' from current driver');
                        exit(1);
                        return false;
                }
        }

        // Get Vendor and Device IDs for the passthru device
        $strVendor = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/vendor');
        $strDevice = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/device');

        // Attempt to bind driver to vfio-pci
        if (file_put_contents('/sys/bus/pci/drivers/vfio-pci/new_id', $strVendor . ' ' . $strDevice) === false) {
                file_put_contents('php://stderr', 'Failed to bind device ' . $strPassthruDeviceShort . ' to vfio-pci driver');
                exit(1);
                return false;
        }

        return true;
}