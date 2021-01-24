<?php
/* Copyright 2021, Simon Fairweather
 *
 * Based on original code from Guilherme Jardim and Dan Landon
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$plugin = "unraid.usbip-gui";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$translations = file_exists("$docroot/webGui/include/Translations.php");

if ($translations) {
	/* add translations */
	$_SERVER['REQUEST_URI'] = 'USBIP_Devices' ;
	require_once "$docroot/webGui/include/Translations.php";
} else {
	/* legacy support (without javascript) */
	$noscript = true;
	require_once "$docroot/plugins/$plugin/include/Legacy.php";
}

require_once("plugins/{$plugin}/include/lib_usbip.php");
require_once("webGui/include/Helpers.php");

if (isset($_POST['display'])) $display = $_POST['display'];
if (isset($_POST['var'])) $var = $_POST['var'];
check_usbip_modules() ;


/*
function netmasks($netmask, $rev = false)
{
	$netmasks = [	"255.255.255.252"	=> "30",
					"255.255.255.248"	=> "29",
					"255.255.255.240"	=> "28",
					"255.255.255.224"	=> "27",
					"255.255.255.192"	=> "26",
					"255.255.255.128"	=> "25",
					"255.255.255.0"		=> "24",
					"255.255.254.0"		=> "23",
					"255.255.252.0"		=> "22",
					"255.255.248.0"		=> "21",
					"255.255.240.0" 	=> "20",
					"255.255.224.0" 	=> "19",
					"255.255.192.0" 	=> "18",
					"255.255.128.0" 	=> "17",
					"255.255.0.0"		=> "16",
				];
	return $rev ? array_flip($netmasks)[$netmask] : $netmasks[$netmask];
}
*/

function make_mount_button($device) {
	global $paths, $Preclear, $loaded_usbip_host;

	$button = "<span><button device='{$device["BUSID"]}' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

		if ($device["isflash"] == true ) {
		 $disabled = "disabled"	;
		 $button = sprintf($button, $context, 'urflash', $disabled, 'fa fa-erase', _('UnRaid Flash'));
		} 

			if ($loaded_usbip_host == "0")
			 {
			$disabled = "disabled <a href=\"#\" title='"._("usbip_host module not loaded")."'" ;
		 } 
			else 
			{
			 $disabled = "enabled"; 
			}

		if ($device["DRIVER"] == "usbip-host") {
		$context = "disk";
		$button = sprintf($button, $context, 'unbind', $disabled, 'fa fa-erase', _('Unbind'));
		}
		else {
			$context = "disk";
			$button = sprintf($button, $context, 'bind', $disabled, 'fa fa-import', _('Bind'));
		}
	
	return $button;
}
function make_attach_button($device,$busid) {
	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist ;

	$button = "<span><button hostport='".$device.";".ltrim($busid)."' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

	if ($loaded_vhci_hcd == "0")
		{
			$disabled = "disabled <a href=\"#\" title='"._("vhci_hcd module not loaded")."'" ;
		} else {
			$disabled = "enabled"; 
		}

	$context = "disk";
	$button = sprintf($button, $context, 'attach', $disabled, 'fa fa-import', _('Attach'));
	
	return $button;
}

function make_detach_button($port) {
	global $paths, $Preclear;

	$button = "<span><button port='{$port}' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

	if ($device["DRIVER"] == "usbip-host") {
		$context = "disk";
		$button = sprintf($button, $context, 'detach', $disabled, 'fa fa-erase', _('detact'));
		}
		else {
			$context = "disk";
			$button = sprintf($button, $context, 'detach', $disabled, 'fa fa-import', _('detach'));
		}

	return $button;
}

switch ($_POST['action']) {
	case 'get_content':
		global $paths, $usbip_cmds_exist;

		if (!$usbip_cmds_exist || !$loaded_usbip_host || !$loaded_vhci_hcd) {

			$notice="Following are missing or not loaded:" ;
			if (!$usbip_cmds_exist) $notice.=" USBIP Commands" ;
			if (!$loaded_usbip_host) $notice.=" usbip_host module" ;
			if (!$loaded_vhci_hcd) $notice.=" vhci_hcd module" ;
		    echo "<p class='notice 	'>"._($notice).".</p>";
		   }

		usbip_log("Starting page render [get_content]", "DEBUG");
		$time		 = -microtime(true);

		/* Check for a recent hot plug event. */
		$tc = $paths['hotplug_status'];
		$hotplug = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : "no";
		if ($hotplug == "yes") {
			exec("/usr/local/sbin/emcmd 'cmdHotplug=apply'");
			file_put_contents($tc, json_encode('no'));
		}

		/* Disk devices */
		$usbip = get_all_usb_info();
		#var_dump($disks) ;
		echo "<div id='usbip_tab' class='show-disks'>";
		#echo "<table class='disk_status wide disk_mounts'><thead><tr><td>"._('BusID')."</td><td>"._('Action')."</td><td>"._('Subsystem/Driver')."</td><td>"._('Vendor:Product').".</td><td>"._('Reads')."</td><td>"._('Writes')."</td><td>"._('Settings')."</td><td>"._('FS')."</td><td>"._('Size')."</td><td>"._('Used')."</td><td>"._('Free')."</td><td>"._('Log')." idden</td></tr></thead>";
		echo "<table class='disk_status wide disk_mounts'><thead><tr><td>"._('BusID')."</td><td>"._('Action')."</td><td>"._('Subsystem/Driver')."</td><td>"._('Vendor:Product').".</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td></tr></thead>";

		
		echo "<tbody>";
	
		if ( count($usbip) ) {
			foreach ($usbip as $disk => $detail) {
				#echo "<tr class='toggle-disk'>";
				$hdd_serial = "<a href=\"#\" title='"._("Disk Log Information")."' onclick=\"openBox('/webGui/scripts/disk_log&amp;arg1={$disk}','Disk Log Information',600,900,false);return false\"><i class='fa fa-hdd-o icon'></i></a>";
				$hdd_serial .="<span title='"._("Click to view/hide partitions and mount points")."' class='exec toggle-hdd' hdd='{$disk}'></span>";
				#$hdd_serial .="<span><i class='fa fa-minus-square fa-append grey-orb'></i></span>";
				$detail["BUSID"] = $disk ;
				$mbutton = make_mount_button($detail);		
			#	echo "<tr class='toggle-disk'>";
					/* Device serial number */
			    echo "<td>{$hdd_serial}{$disk}</td>";
				/* Device serial number */
				#echo "<td>{$disk}</td>";
				/* Mount button */
				echo "<td class='mount'>{$mbutton}</td>";
				/* Device driver */
				echo "<td>".$detail["SUBSYSTEM"]."/".$detail["DRIVER"]."</td>";
					/* Device driver */
					echo "<td>".$detail["ID_VENDOR"].":".$detail["ID_MODEL"] ;

		echo "</tr>";	
			}
		} else {
			echo "<tr><td colspan='12' style='text-align:center;'>"._('No Bindable Devices available').".</td></tr>";
	

		}
		echo "</tbody></table></div>";

		/* Remote USBIP Servers */
		echo "<div id='rmtip_tab' class='show-rmtip'>";
		
		echo "<div class='show-rmtip' id='rmtip_tab'><div id='title'><span class='left'><img src='/plugins/$plugin/icons/nfs.png' class='icon'>"._('Remote USBIP Hosts')." &nbsp;</span></div>";
		#echo "<table class='disk_status wide remote_ip'><thead><tr><td>"._('Remote host')."</td><td>"._('Busid')."</td><td>"._('Action')."</td><td>"._('Vendor:Product(Additional Details)')."</td><td></td><td>"._('Remove')."</td><td>"._('Settings')."</td><td></td><td></td><td>"._('Size')."</td><td>"._('Used')."</td><td>"._('Free')."</td><td>"._('Log')."</td></tr></thead>";
		echo "<table class='disk_status wide remote_ip'><thead><tr><td>"._('Remote host')."</td><td>"._('Busid')."</td><td>"._('Action')."</td><td>"._('Vendor:Product(Additional Details)')."</td><td></td><td>"._('Remove')."</td><td>"._('')."</td><td></td><td></td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td></tr></thead>";
		echo "<tbody>";
		$ds1 = time();
		$remote_usbip = get_remote_usbip();
		ksort($remote_usbip) ;
		$ii=1 ;
		#var_dump($remote_usbip) ;
		usbip_log("get_remote_usbip: ".($ds1 - microtime(true))."s!","DEBUG");
		if (count($remote_usbip)) {
			foreach ($remote_usbip as $key => $remote)
			{


				$cmd_return=parse_usbip_remote($key) ;
				#var_dump($cmd_return) ;
				$busids = $cmd_return[$key] ;
				if (isset($busids)) {
				foreach ($busids as $busidkey => $busiddetail)
				{
				echo "<tbody>" ;
	
				
				$hostport = $key."".ltrim($busidkey) ;
				$hostport = "HP".$ii ;
				echo "<tr class='toggle-rmtips'><td><i class='fa fa-minus-circle orb grey-orb'></i>"; 

				echo $key."</td>";
				

				$abutton = make_attach_button($key, $busidkey);		
				
				echo "<td>".$busidkey."</td><td>" ;
			
				echo "<class='attach'>{$abutton}   ";
				echo "<td><span title='"._("Click to view/hide additional Remote details")."' class='exec toggle-rmtip' hostport='{$hostport}'><i class='fa fa-plus-square fa-append'></i></span>".$busiddetail["vendor"].$busiddetail["product"]."</td><td>" ;
				#var_dump($busiddetail) ;
				$detail_lines=$busiddetail["detail"] ;
				echo "</td><td title='"._("Remove Remote Host configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;' onclick='remove_remote_host_config(\"{$key}\")'><i class='fa fa-remove hdd'></a></td></tr>" ;

		
			foreach($detail_lines as $line)
			{
				
				
				$style = "style='display:none;' " ;
				#$style = "" ;
				#<tr class='toggle-parts toggle-".basename($disk['device'])."' name='toggle-".basename($disk['device'])."' $style>"
				echo "<tr class='toggle-parts toggle-rmtip-".$hostport."' name='toggle-rmtip-".$hostport."'".$style.">";
				echo "<td></td><td></td><td></td><td>&nbsp&nbsp&nbsp&nbsp&nbsp".htmlspecialchars($line)."</td></tr>" ;
			

				
				
			}
		
			$ii++ ;
				echo "</tr>";
				}
			}
			}
		}


		if (! count($remote_usbip)) {
			echo "<tr><td colspan='13' style='text-align:center;'>"._('No Remote Systems configured').".</td></tr>";
		}
		echo "</tbody></table>";
		
		echo "<button onclick='add_remote_host()'>"._('Add Remote System')."</button>";
		echo "</div>";
		echo "</div>";


		echo "<div id='port_tab' class='show-ports'>";
		$ct = "";
		$port=parse_usbip_port() ;
		#echo "<tr class='toggle-ports'>";
		#var_dump($port) ;
		echo "<div class='show-ports' id='ports_tab'><div id='title'><span class='left'><img src='/plugins/{$plugin}/icons/historical.png' class='icon'>"._('Attached Ports')."</span></div>";
		echo "<table class='disk_status wide usb_absent'><thead><tr><td>"._('Device')."</td><td>"._('HUB Port=>Remote host')."</td><td>"._('Action')."</td><td></td><td></td><td></td><td></td><td></td><td>"._('')."</td><td>"._('')."</td></tr></thead>" ;

		foreach ($port as $portkey => $portline) {
			$dbutton = make_detach_button($portkey);
			$ct = "";
			$ct .= "<tr class='toggle-ports'><td><i class='fa fa-minus-circle orb grey-orb'></i><span title='"._("Click to view/hide additional details")."' class='exec toggle-port' port='{$portkey}'><i class='fa fa-plus-square fa-append'></i></span> Port:".$portkey."</td><td>".$portline[2]."</td><td>".$dbutton."</td>";
			$ct .= "";
				$ct .= "<td></td><td></td><td></td><td></td><td></td>";
				$ct .= "<td><a title='"._("Edit Historical Device Settings and Script")."' hidden href='/Main/EditSettings?s=&l=".urlencode(basename($portkey))."&p=".urlencode("1")."&t=TRUE'><i class='fa fa-gears'></i></a></td>";
				$ct .= "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;' hidden onclick='remove_disk_config(\"{$serial}\")'><i class='fa fa-remove hdd'></a></td></tr>";
		

     
 		
		echo "<tbody>{$ct}";
		
		$index = 0;
			foreach($portline as $desc)
			{
				if ($index != 2) {
				
				$style = "style='display:none;'" ;
				#<tr class='toggle-parts toggle-".basename($disk['device'])."' name='toggle-".basename($disk['device'])."' $style>"
				echo "<tr class='toggle-parts toggle-port-".basename($portkey)."' name='toggle-port-".basename($portkey)."' $style>";
				echo "<td></td><td>".htmlspecialchars($desc)."</td></tr>";
				
				}
				$index++ ;
			}
		
	
		}

		echo "</tr>";


		if ( ! count($port)) {
			echo "<tr><td colspan='13' style='text-align:center;'>"._('No ports in use').".</td></tr>";
		}


		
		echo "</tbody></table></div>";

 		usbip_log("Total render time: ".($time + microtime(true))."s", "DEBUG");
		break;

/*	case 'detect':
		global $paths;


		/* Check to see if disk status has changed. */
/*		$status = array();
		$tc = $paths['dev_status'];
		$previous = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
		$sf	= $paths['dev_state'];
		if (is_file($sf)) {
			$devs = parse_ini_file($sf, true);
			foreach ($devs as $d) {
				$name = $d['name'];
				$status[$name]['running'] = $d['spundown'] == '0' ? "yes" : "no";
				$curr = $status[$name]['running'];
				$prev = $previous[$name]['running'];
				if (! is_file($GLOBALS['paths']['reload']) && ($curr != $prev)) {
					@touch($GLOBALS['paths']['reload']);
				}
			}
		#	file_put_contents($tc, json_encode($status));
		}

		echo json_encode(array("reload" => is_file($paths['reload']), "diskinfo" => 0));
		break;
*/
	case 'refresh_page':
		if (! is_file($GLOBALS['paths']['reload'])) {
			@touch($GLOBALS['paths']['reload']);
		}
		break;
/*
	case 'remove_hook':
		@unlink($paths['reload']);
		break;

	case 'update_ping':
		global $paths;

		/* Refresh the ping status in the background. */
/*		$config_file = $paths['samba_mount'];
		$samba_mounts = @parse_ini_file($config_file, true);
		if (is_array($samba_mounts)) {
			foreach ($samba_mounts as $device => $mount) {
				$tc = $paths['ping_status'];
				$ping_status = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
				$server = $mount['ip'];
				$changed = ($ping_status[$server]['changed'] == 'yes') ? TRUE : FALSE;
				$mounted = is_mounted($device);
				is_samba_server_online($server, $mounted);
				if (! is_file($GLOBALS['paths']['reload']) && ($changed)) {
					$no_pings = $ping_status[$server]['no_pings'];
					$online = $ping_status[$server]['online'];
					$ping_status[$server] = array('timestamp' => time(), 'no_pings' => $no_pings, 'online' => $online, 'changed' => 'no');
					file_put_contents($tc, json_encode($ping_status));
					@touch($GLOBALS['paths']['reload']);
				}
			}
		}
		break;
*/

	case 'bind':
		$device = urldecode($_POST['device']);
		$cmd_usbip_bind= "usbip bind -b ".$device ;
		exec($cmd_usbip_bind, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;

	case 'unbind':
		$device = urldecode($_POST['device']);
		$cmd_usbip_unbind= "usbip unbind -b ".$device ;
		exec($cmd_usbip_unbind, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;

	case 'detach':
		$port = urldecode($_POST['port']);
		$cmd_usbip_detach= "usbip detach -p ".$port ;
		exec($cmd_usbip_detach, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;
		
	case 'attach':
		$hostport = urldecode($_POST['hostport']);
		$explode= explode(";",$hostport) ;
		$host = $explode[0] ;
		$port = $explode[1] ;
		$cmd_usbip_attach= "usbip attach -r ".$host." -b ".$port ;
		exec($cmd_usbip_attach, $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
		break;	



	case 'rescan_disks':
		exec("plugins/{$plugin}/scripts/copy_config.sh");
		$tc = $paths['hotplug_status'];
		$hotplug = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : "no";
		if ($hotplug == "no") {
			file_put_contents($tc, json_encode('yes'));
			@touch($GLOBALS['paths']['reload']);
		}
		break;

	case 'list_nfs_hosts':
		$network = $_POST['network'];
		foreach ($network as $iface)
		{
			$ip = $iface['ip'];
			$netmask = $iface['netmask'];
			echo shell_exec("/usr/bin/timeout -s 13 5 plugins/{$plugin}/scripts/port_ping.sh {$ip} {$netmask} 3240 2>/dev/null | sort -n -t . -k 1,1 -k 2,2 -k 3,3 -k 4,4");
		}
		break;

		case 'add_remote_host':
		$rc = TRUE;

		$ip = urldecode($_POST['IP']);
		$ip = implode("",explode("\\", $ip));
		$ip = stripslashes(trim($ip));

		if ($ip) {
			$device = $ip ;
			$device = str_replace("$", "", $device);
		
			set_remote_host_config("{$device}", "ip", (is_ip($ip) ? $ip : strtoupper($ip)));


			/* Refresh the ping status */
			is_usbip_server_online($ip, FALSE);
		}
		echo json_encode($rc);
		break;

	case 'remove_remote_host_config':
		$ip = urldecode(($_POST['ip']));
		echo json_encode(remove_config_remote_host($ip));
		break;
	}
?>
