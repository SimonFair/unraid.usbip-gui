<?php
/* Copyright 2021, Simon Fairweather
 *
 * Based on original code from  Guilherme Jardim and Dan Landon
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

function render_used_and_free($partition, $mounted) {
	global $display;

	if (strlen($partition['target']) && $mounted) {
		$free_pct = $partition['size'] ? round(100*$partition['avail']/$partition['size']) : 0;
		$used_pct = 100-$free_pct;
	    if ($display['text'] % 10 == 0) {
			$o = "<td>".my_scale($partition['used'], $unit)." $unit</td>";
		} else {
			$o = "<td><div class='usage-disk'><span style='margin:0;width:$used_pct%' class='".usage_color($display,$used_pct,false)."'></span><span>".my_scale($partition['used'], $unit)." $unit</span></div></td>";
		}
	    if ($display['text'] < 10 ? $display['text'] % 10 == 0 : $display['text'] % 10 != 0) {
			$o .= "<td>".my_scale($partition['avail'], $unit)." $unit</td>";
		} else {
			$o .= "<td><div class='usage-disk'><span style='margin:0;width:$free_pct%' class='".usage_color($display,$free_pct,true)."'></span><span>".my_scale($partition['avail'], $unit)." $unit</span></div></td>";
		}
	} else {
		$o = "<td>-</td><td>-</td>";
	}
	return $o;
}

function render_used_and_free_disk($disk, $mounted) {
	global $display;

	if ($mounted) {
		$size	= 0;
		$avail	= 0;
		$used	= 0;
		foreach ($disk['partitions'] as $partition) {
			$size	+= $partition['size'];
			$avail	+= $partition['avail'];
			$used 	+= $partition['used'];
		}
		$free_pct = $size ? round(100*$avail/$size) : 0;
		$used_pct = 100-$free_pct;
	    if ($display['text'] % 10 == 0) {
			$o = "<td>".my_scale($used, $unit)." $unit</td>";
		} else {
			$o = "<td><div class='usage-disk'><span style='margin:0;width:$used_pct%' class='".usage_color($display,$used_pct,false)."'></span><span>".my_scale($used, $unit)." $unit</span></div></td>";
		}
	    if ($display['text'] < 10 ? $display['text'] % 10 == 0 : $display['text'] % 10 != 0) {
			$o .= "<td>".my_scale($avail, $unit)." $unit</td>";
		} else {
			$o .= "<td><div class='usage-disk'><span style='margin:0;width:$free_pct%' class='".usage_color($display,$free_pct,true)."'></span><span>".my_scale($avail, $unit)." $unit</span></div></td>";
		}
	} else {
		$o = "<td>-</td><td>-</td>";
	}
	return $o;
}

function render_partition($disk, $partition, $total=FALSE) {
	global $plugin;

	if (! isset($partition['device'])) return array();
	$out = array();

	$mounted =	$partition['mounted'];
	$cmd = $partition['command'];
	if ($mounted && is_file($cmd)) {
		$script_partition = $partition['fstype'] == "crypto_LUKS" ? $partition['luks'] : $partition['device'];
		if ((! is_script_running($cmd)) & (! is_script_running($partition['user_command'], TRUE))) {
			$fscheck = "<a title='"._("Execute Script as udev simulating a device being installed")."' class='exec' onclick='openWindow_fsck(\"/plugins/{$plugin}/include/script.php?device={$script_partition}&type="._('Done')."\",\"Execute Script\",600,900);'><i class='fa fa-flash partition-script'></i></a>{$partition['part']}";
		} else {
			$fscheck = "<i class='fa fa-flash partition-script'></i>{$partition['part']}";
		}
	} elseif ( (! $mounted && $partition['fstype'] != 'btrfs') ) {
		$fscheck = "<a title='"._('File System Check')."' class='exec' onclick='openWindow_fsck(\"/plugins/{$plugin}/include/fsck.php?device={$partition['device']}&fs={$partition['fstype']}&luks={$partition['luks']}&serial={$partition['serial']}&check_type=ro&type="._('Done')."\",\"Check filesystem\",600,900);'><i class='fa fa-check partition-hdd'></i></a>{$partition['part']}";
	} else {
		$fscheck = "<i class='fa fa-check partition-hdd'></i>{$partition['part']}";
	}

	$rm_partition = (file_exists("/usr/sbin/parted") && get_config("Config", "destructive_mode") == "enabled" && (! $disk['partitions'][0]['pass_through'])) ? "<span title='"._("Remove Partition")."' device='{$partition['device']}' class='exec' style='color:#CC0000;font-weight:bold;' onclick='rm_partition(this,\"{$disk['device']}\",\"{$partition['part']}\");'><i class='fa fa-remove hdd'></i></span>" : "";
	$mpoint = "<span>{$fscheck}";
	$mount_point = basename($partition['mountpoint']);
	if ($mounted) {
		$mpoint .= "<i class='fa fa-folder-open partition-hdd'></i><a title='"._("Browse Disk Share")."' href='/Main/Browse?dir={$partition['mountpoint']}'>{$mount_point}</a></span>";
	} else {
		$mount_point = basename($partition['mountpoint']);
		$device = ($partition['fstype'] == "crypto_LUKS") ? $partition['luks'] : $partition['device'];
		$mpoint .= "<i class='fa fa-pencil partition-hdd'></i><a title='"._("Change Disk Mount Point")."' class='exec' onclick='chg_mountpoint(\"{$partition['serial']}\",\"{$partition['part']}\",\"{$device}\",\"{$partition['fstype']}\",\"{$mount_point}\");'>{$mount_point}</a>";
		$mpoint .= "{$rm_partition}</span>";
	}
	$temp = my_temp($disk['temperature']);
	$mbutton = make_mount_button($partition);

	get_config($disk['serial'], "show_partitions") != 'yes' ? $style = "style='display:none;'" : $style = "";
	$out[] = "<tr class='toggle-parts toggle-".basename($disk['device'])."' name='toggle-".basename($disk['device'])."' $style>";
	$out[] = "<td></td>";
	$out[] = "<td>{$mpoint}</td>";
	$out[] = "<td class='mount'>{$mbutton}</td>";
	$fstype = $partition['fstype'];
	if ($total) {
		foreach ($disk['partitions'] as $part) {
			if ($part['fstype']) {
				$fstype = $part['fstype'];
				break;
			}
		}
	}

	/* Reads and writes */ /*
	if ($total) {
		$out[] = "<td>".my_scale($part['reads'],$unit,0,null,1)."</td>";
		$out[] = "<td>".my_scale($part['writes'],$unit,0,null,1)."</td>";
	} else {
		$out[] = "<td></td>";
		$out[] = "<td></td><td></td>";
	}

	$title = _("Edit Device Settings and Script").".";
	if ($total) {
		$title .= "\n"._("Pass Through").":  ";
		$title .= ($partition['pass_through'] == 'yes') ? "On" : "Off";
		$title .= "   "._("Read Only").": ";
		$title .= ($partition['read_only'] == 'yes') ? "On" : "Off";
		$title .= "   "._("Automount").": ";
		$title .= ($partition['automount'] == 'yes') ? "On" : "Off";
		$title .=  "   ";
	} else {
		$title .= "\n";
	}
	$title .= _("Share").": ";
	$title .= ($partition['shared'] == 'yes') ? "On" : "Off";

	$out[] = "<td><a title='$title' href='/Main/EditSettings?s=".urlencode($partition['serial'])."&l=".urlencode(basename($partition['mountpoint']))."&p=".urlencode($partition['part'])."&m=".urlencode(json_encode($partition))."&t=".$total."'><i class='fa fa-gears'></i></a></td>";
	if ($total) {
		$mounted_disk = FALSE;
		foreach ($disk['partitions'] as $part) {
			if ($part['mounted']) {
				$mounted_disk = TRUE;
				break;
			}
		}
	}

	$out[] = "<td>".($fstype == "crypto_LUKS" ? luks_fs_type($partition['mountpoint']) : $fstype)."</td>";
	if ($total) {
		$out[] = render_used_and_free_disk($disk, $mounted_disk);
	} else {
		$out[] = "<td>".my_scale($partition['size'], $unit)." $unit</td>";
		$out[] = render_used_and_free($partition, $mounted);
	}
	$out[] = "<td><a title='"._("View Device Script Log")."' href='/Main/ScriptLog?s=".urlencode($partition['serial'])."&l=".urlencode(basename($partition['mountpoint']))."&p=".urlencode($partition['part'])."'><i class='fa fa-align-left'></i></a></td>";
	$out[] = "</tr>";
	return $out;
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
		 } 
			else 
			{
			 $disabled = "enabled"; 
			}


			$context = "disk";
			$button = sprintf($button, $context, 'attach', $disabled, 'fa fa-import', _('Attach'));
	

	return $button;
}

function make_detach_button($port) {
	global $paths, $Preclear;

	$button = "<span><button port='{$port}' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";


	#if ($device['size'] == 0) {
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
			#echo "<p class='notice 	'>"._('Targetcli in use unable to read status and config.').".</p>";
		    echo "<p class='notice 	'>"._($notice).".</p>";
		   }

		unassigned_log("Starting page render [get_content]", "DEBUG");
		$time		 = -microtime(true);

		/* Check for a recent hot plug event. */
		$tc = $paths['hotplug_status'];
		$hotplug = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : "no";
		if ($hotplug == "yes") {
			exec("/usr/local/sbin/emcmd 'cmdHotplug=apply'");
			file_put_contents($tc, json_encode('no'));
		}

		/* Disk devices */
		$disks = get_all_usb_info();
		#var_dump($disks) ;
		echo "<div id='disks_tab' class='show-disks'>";
		#echo "<table class='disk_status wide disk_mounts'><thead><tr><td>"._('BusID')."</td><td>"._('Action')."</td><td>"._('Subsystem/Driver')."</td><td>"._('Vendor:Product').".</td><td>"._('Reads')."</td><td>"._('Writes')."</td><td>"._('Settings')."</td><td>"._('FS')."</td><td>"._('Size')."</td><td>"._('Used')."</td><td>"._('Free')."</td><td>"._('Log')." idden</td></tr></thead>";
		echo "<table class='disk_status wide disk_mounts'><thead><tr><td>"._('BusID')."</td><td>"._('Action')."</td><td>"._('Subsystem/Driver')."</td><td>"._('Vendor:Product').".</td><td>"._('Reads')."</td><td>"._('Writes')."</td><td>"._('Settings')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')."</td><td>"._('')." idden</td></tr></thead>";

		
		echo "<tbody>";
	
		if ( count($disks) ) {
			foreach ($disks as $disk => $detail) {
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
		echo "<table class='disk_status wide remote_ip'><thead><tr><td>"._('Remote host')."</td><td>"._('Busid')."</td><td>"._('Action')."</td><td>"._('Vendor:Product(Additional Details)')."</td><td></td><td>"._('Remove')."</td><td>"._('Settings')."</td><td></td><td></td><td>"._('Size')."</td><td>"._('Used')."</td><td>"._('Free')."</td><td>"._('Log')."</td></tr></thead>";
		echo "<tbody>";
		$ds1 = time();
		$remote_usbip = get_remote_usbip();
		$ii=1 ;
		#var_dump($remote_usbip) ;
		unassigned_log("get_remote_usbip: ".($ds1 - microtime(true))."s!","DEBUG");
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
				echo "<td></td><td></td><td></td><td>".htmlspecialchars($line)."</td></tr>" ;
			

				
				
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
		echo "<table class='disk_status wide usb_absent'><thead><tr><td>"._('Device')."</td><td>"._('HUB Port=>Remote host')."</td><td></td><td></td><td></td><td></td><td></td><td></td><td>"._('')."</td><td>"._('')."</td></tr></thead>" ;

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

 		unassigned_log("Total render time: ".($time + microtime(true))."s", "DEBUG");
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

	case 'get_content_json':
		unassigned_log("Starting json reply action [get_content_json]", "DEBUG");
		$time = -microtime(true);
		$disks = get_all_disks_info();
		echo json_encode($disks);
		unassigned_log("Total render time: ".($time + microtime(true))."s", "DEBUG");
		break;

	/*	CONFIG	*/
/*	case 'automount':
		$serial = urldecode(($_POST['device']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_automount($serial, $status) ));
		break;

	case 'show_partitions':
		$serial = urldecode(($_POST['serial']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => set_config($serial, "show_partitions", ($status == "true") ? "yes" : "no")));
		break;

	case 'background':
		$device = urldecode(($_POST['device']));
		$part = urldecode(($_POST['part']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => set_config($device, "command_bg.{$part}", $status)));
		break;

	case 'set_command':
		$serial = urldecode(($_POST['serial']));
		$part = urldecode(($_POST['part']));
		$cmd = urldecode(($_POST['command']));
		set_config($serial, "user_command.{$part}", urldecode($_POST['user_command']));
		echo json_encode(array( 'result' => set_config($serial, "command.{$part}", $cmd)));
		break;

	case 'remove_config':
		$serial = urldecode(($_POST['serial']));
		echo json_encode(remove_config_disk($serial));
		break;

	case 'toggle_share':
		$info = json_decode(html_entity_decode($_POST['info']), true);
		$status = urldecode(($_POST['status']));
		$result = toggle_share($info['serial'], $info['part'],$status);
		if ($result && strlen($info['target']) && $info['mounted']) {
			add_smb_share($info['mountpoint'], $info['label']);
			add_nfs_share($info['mountpoint']);
		} elseif ($info['mounted']) {
			rm_smb_share($info['mountpoint'], $info['label']);
			rm_nfs_share($info['mountpoint']);
		}
		echo json_encode(array( 'result' => $result));
		break;

	case 'toggle_read_only':
		$serial = urldecode(($_POST['serial']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_read_only($serial, $status) ));
		break;

	case 'toggle_pass_through':
		$serial = urldecode(($_POST['serial']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_pass_through($serial, $status) ));
		break;

	/*	USBIP 	*/
/*	case 'xmount':
		$device = urldecode($_POST['device']);
		exec("plugins/{$plugin}/scripts/rc.unassigned mount '$device' &>/dev/null", $out, $return);
		echo json_encode(["status" => $return ? false : true ]);
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


/*	case 'mount':
			$device = urldecode($_POST['device']);
			$cmd_usbip_bind="usbip bind-b ".$device ;
			exec($cmd_usbip_bind, $out, $return);
			echo json_encode(["status" => $return ? false : true ]);
			break;
	
	case 'unbind':
			
			$device = urldecode($_POST['device']);
			$cmd_usbip_unbind="usbip unbind-b ".$device ;
			exec($cmd_usbip_unbind, $out, $return);
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

	case 'format_disk':
		$device = urldecode($_POST['device']);
		$fs = urldecode($_POST['fs']);
		$pass = urldecode($_POST['pass']);
		@touch(sprintf($paths['formatting'],basename($device)));
		echo json_encode(array( 'status' => format_disk($device, $fs, $pass)));
		@unlink(sprintf($paths['formatting'],basename($device)));
		break;

	/*	SAMBA	*/
/*	case 'list_samba_hosts':
		/* $workgroup = urldecode($_POST['workgroup']); */
/*		$network = $_POST['network'];
		$names = [];
		foreach ($network as $iface)
		{
			$ip = $iface['ip'];
			$netmask = $iface['netmask'];
			exec("plugins/{$plugin}/scripts/port_ping.sh {$ip} {$netmask} 445", $hosts);
			foreach ($hosts as $host) {
				$name=trim(shell_exec("/usr/bin/nmblookup -A '$host' 2>/dev/null | grep -v 'GROUP' | grep -Po '[^<]*(?=<00>)' | head -n 1"));
				$names[]= $name ? $name : $host;
			}
			natsort($names);
		}
		echo implode(PHP_EOL, $names);
		/* exec("/usr/bin/nmblookup --option='disable netbios'='No' '$workgroup' | awk '{print $1}'", $output); */
		/* echo timed_exec(10, "/usr/bin/smbtree --servers --no-pass | grep -v -P '^\w+' | tr -d '\\' | awk '{print $1}' | sort"); */
/*		break;

	case 'list_samba_shares':
		$ip = urldecode($_POST['IP']);
		$user = isset($_POST['USER']) ? $_POST['USER'] : NULL;
		$pass = isset($_POST['PASS']) ? $_POST['PASS'] : NULL;
		$domain = isset($_POST['DOMAIN']) ? $_POST['DOMAIN'] : NULL;
		file_put_contents("{$paths['authentication']}", "username=".$user."\n");
		file_put_contents("{$paths['authentication']}", "password=".$pass."\n", FILE_APPEND);
		file_put_contents("{$paths['authentication']}", "domain=".$domain."\n", FILE_APPEND);
		$list = shell_exec("/usr/bin/smbclient -t2 -g -L '$ip' --authentication-file='{$paths['authentication']}' 2>/dev/null | /usr/bin/awk -F'|' '/Disk/{print $2}' | sort");
		exec("/bin/shred -u ".$paths['authentication']);
		echo $list;
		break;

	/*	NFS	*/
	case 'list_nfs_hosts':
		$network = $_POST['network'];
		foreach ($network as $iface)
		{
			$ip = $iface['ip'];
			$netmask = $iface['netmask'];
			echo shell_exec("/usr/bin/timeout -s 13 5 plugins/{$plugin}/scripts/port_ping.sh {$ip} {$netmask} 3240 2>/dev/null | sort -n -t . -k 1,1 -k 2,2 -k 3,3 -k 4,4");
		}
		break;

/*	case 'list_nfs_shares':
		$ip = urldecode($_POST['IP']);
		$rc = timed_exec(10, "/usr/sbin/showmount --no-headers -e '{$ip}' 2>/dev/null | rev | cut -d' ' -f2- | rev | sort");
		echo $rc ? $rc : " ";
		break;

	/* SMB SHARES */
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
/*
	case 'samba_automount':
		$device = urldecode(($_POST['device']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_samba_automount($device, $status) ));
		break;

	case 'samba_share':
		$device = urldecode(($_POST['device']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => toggle_samba_share($device, $status) ));
		break;

	case 'toggle_samba_share':
		$info = json_decode(html_entity_decode($_POST['info']), true);
		$status = urldecode(($_POST['status']));
		$result = toggle_samba_share($info['device'], $status);
		if ($result && strlen($info['target']) && $info['mounted']) {
			add_smb_share($info['mountpoint'], $info['device']);
			add_nfs_share($info['mountpoint']);
		} elseif ($info['mounted']) {
			rm_smb_share($info['mountpoint'], $info['device']);
			rm_nfs_share($info['mountpoint']);
		}
		echo json_encode(array( 'result' => $result));
		break;

	case 'samba_background':
		$device = urldecode(($_POST['device']));
		$status = urldecode(($_POST['status']));
		echo json_encode(array( 'result' => set_samba_config($device, "command_bg", $status)));
		break;

	case 'set_samba_command':
		$device = urldecode(($_POST['device']));
		$cmd = urldecode(($_POST['command']));
		set_samba_config($device, "user_command", urldecode($_POST['user_command']));
		echo json_encode(array( 'result' => set_samba_config($device, "command", $cmd)));
		break;



	/*	MISC */


	}
?>
