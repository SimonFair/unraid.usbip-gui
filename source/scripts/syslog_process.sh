#!/bin/bash

DEBUG=false ;

$DEBUG && Log "debug: usbip syslog filter triggered"

/usr/local/emhttp/plugins/unraid.usbip-gui/scripts/rc.unraid.usbip-gui usb_syslog "$1" >/dev/null 2>&1 & disown

exit 0
