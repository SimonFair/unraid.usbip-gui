<?php
/*  Copyright 2021, Simon Fairweather
 *  
 * 
 * based on original code from Guilherme Jardim and Dan Landon
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$plugin = "unraid.usbip-gui";
/* $VERBOSE=TRUE; */

$paths = [  "device_log"		=> "/tmp/{$plugin}/",
			"config_file"		=> "/tmp/{$plugin}/config/{$plugin}.cfg",
			"hdd_temp"			=> "/var/state/{$plugin}/hdd_temp.json",
			"run_status"		=> "/var/state/{$plugin}/run_status.json",
			"ping_status"		=> "/var/state/{$plugin}/ping_status.json",
			"hotplug_status"	=> "/var/state/{$plugin}/hotplug_status.json",
			"remote_usbip"		=> "/tmp/{$plugin}/config/remote_usbip.cfg",
		];

$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$disks = @parse_ini_file("$docroot/state/disks.ini", true);

#########################################################
#############        MISC FUNCTIONS        ##############
#########################################################



function is_ip($str) {
	return filter_var($str, FILTER_VALIDATE_IP);
}

function _echo($m) { echo "<pre>".print_r($m,TRUE)."</pre>";}; 

function save_ini_file($file, $array) {
	global $plugin;

	$res = array();
	foreach($array as $key => $val) {
		if(is_array($val)) {
			$res[] = PHP_EOL."[$key]";
			foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
		} else {
			$res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
		}
	}

	/* Write changes to tmp file. */
	file_put_contents($file, implode(PHP_EOL, $res));

	/* Write changes to flash. */
	$file_path = pathinfo($file);
	if ($file_path['extension'] == "cfg") {
		file_put_contents("/boot/config/plugins/".$plugin."/".basename($file), implode(PHP_EOL, $res));
	}
}

function usbip_log($m, $type = "NOTICE") {
	global $plugin;

	if ($type == "DEBUG" && ! $GLOBALS["VERBOSE"]) return NULL;
	$m		= print_r($m,true);
	$m		= str_replace("\n", " ", $m);
	$m		= str_replace('"', "'", $m);
	$cmd	= "/usr/bin/logger ".'"'.$m.'"'." -t".$plugin;
	exec($cmd);
}

function listDir($root) {
	$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, 
			RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD);
	$paths = array();
	foreach ($iter as $path => $fileinfo) {
		if (! $fileinfo->isDir()) $paths[] = $path;
	}
	return $paths;
}

function safe_name($string, $convert_spaces=TRUE) {
	$string = stripcslashes($string);
	/* Convert single and double quote to underscore */
	$string = str_replace( array("'",'"', "?"), "_", $string);
	if ($convert_spaces) {
		$string = str_replace(" " , "_", $string);
	}
	$string = htmlentities($string, ENT_QUOTES, 'UTF-8');
	$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
	$string = preg_replace('/[^A-Za-z0-9\-_] /', '', $string);
	return trim($string);
}

function exist_in_file($file, $val) {
	return (preg_grep("%{$val}%", @file($file))) ? TRUE : FALSE;
}


function is_usbip_server_online($ip, $mounted, $background=TRUE) {
	global $paths, $plugin;

	$is_alive = FALSE;
	$server = $ip;
	$tc = $paths['ping_status'];
	$ping_status = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
	if (isset($ping_status[$server])) {
		$is_alive = ($ping_status[$server]['online'] == 'yes') ? TRUE : FALSE;
	}
	if ((time() - $ping_status[$server]['timestamp']) > 15 ) {
		$bk = $background ? "&" : "";
		exec("/usr/local/emhttp/plugins/{$plugin}/scripts/get_ud_stats ping {$tc} {$ip} {$mounted} $bk");
	}

	return $is_alive;
}


function timed_exec($timeout=10, $cmd) {
	$time		= -microtime(true); 
	$out		= shell_exec("/usr/bin/timeout ".$timeout." ".$cmd);
	$time		+= microtime(true);
	if ($time >= $timeout) {
		usbip_log("Error: shell_exec(".$cmd.") took longer than ".sprintf('%d', $timeout)."s!");
		$out	= "command timed out";
	} else {
		usbip_log("Timed Exec: shell_exec(".$cmd.") took ".sprintf('%f', $time)."s!", "DEBUG");
	}
	return $out;
}

#########################################################
############        CONFIG FUNCTIONS        #############
#########################################################

function get_config($sn, $var) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	return (isset($config[$sn][$var])) ? html_entity_decode($config[$sn][$var]) : FALSE;
}

function set_config($sn, $var, $val) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn][$var] = htmlentities($val, ENT_COMPAT);
	save_ini_file($config_file, $config);
	return (isset($config[$sn][$var])) ? $config[$sn][$var] : FALSE;
}

function is_automount($sn, $usb=FALSE) {
	$auto = get_config($sn, "automount");
	$auto_usb = get_config("Config", "automount_usb");
	$pass_through = get_config($sn, "pass_through");
	return ( ($pass_through != "yes" && $auto == "yes") || ( $usb && $auto_usb == "yes" ) ) ? TRUE : FALSE;
}

function is_read_only($sn) {
	$read_only = get_config($sn, "read_only");
	$pass_through = get_config($sn, "pass_through");
	return ( $pass_through != "yes" && $read_only == "yes" ) ? TRUE : FALSE;
}

function is_pass_through($sn) {
	return (get_config($sn, "pass_through") == "yes") ? TRUE : FALSE;
}

function toggle_automount($sn, $status) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn]["automount"] = ($status == "true") ? "yes" : "no";
	save_ini_file($config_file, $config);
	return ($config[$sn]["automount"] == "yes") ? 'true' : 'false';
}

function toggle_read_only($sn, $status) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn]["read_only"] = ($status == "true") ? "yes" : "no";
	save_ini_file($config_file, $config);
	return ($config[$sn]["read_only"] == "yes") ? 'true' : 'false';
}

function toggle_pass_through($sn, $status) {
	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	$config[$sn]["pass_through"] = ($status == "true") ? "yes" : "no";
	save_ini_file($config_file, $config);
	@touch($GLOBALS['paths']['reload']);
	return ($config[$sn]["pass_through"] == "yes") ? 'true' : 'false';
}




#########################################################
############        REMOTE HOST             #############
#########################################################



function get_remote_usbip() {
	global $paths;

	$o = array();
	$config_file = $paths['remote_usbip'];
	$remote_usbip = @parse_ini_file($config_file, true);
	
	if (is_array($remote_usbip)) {
		$o = $remote_usbip  ;
		
	} else {
		usbip_log("Error: unable to get the remote usbip hosts.");
	}
	return $o;
}

function set_remote_host_config($source, $var, $val) {
	$config_file = $GLOBALS["paths"]["remote_usbip"];
	$config = @parse_ini_file($config_file, true);
	$config[$source][$var] = $val;
	save_ini_file($config_file, $config);
	return (isset($config[$source][$var])) ? $config[$source][$var] : FALSE;
}


function remove_config_remote_host($source) {
	$config_file = $GLOBALS["paths"]["remote_usbip"];
	$config = @parse_ini_file($config_file, true);
	if ( isset($config[$source]) ) {
		usbip_log("Removing configuration '$source'.");
	}
			
	unset($config[$source]);
	save_ini_file($config_file, $config);
	return (! isset($config[$source])) ? TRUE : FALSE;
}



#########################################################
############         USBIP FUNCTIONS        #############
#########################################################


/* Check modules loaded and commands exist. */

function check_usbip_modules() {
	global $loaded_usbip_host, $loaded_vhci_hcd, $usbip_cmds_exist, $exists_vhci_hcd, $exists_usbip_host ;

	exec("ls /usr/local/sbin/. | grep -c usbip*", $usbip_cmds_exist_array) ;
	$usbip_cmds_exist = $usbip_cmds_exist_array[0] ;
	
	exec("cat /proc/modules | grep -c vhci_hcd", $loaded_vhci_hcd_array) ;
	$loaded_vhci_hcd = $loaded_vhci_hcd_array[0] ;
	
	exec("cat /proc/modules | grep -c usbip_host", $loaded_usbip_host_array) ;
	$loaded_usbip_host = $loaded_usbip_host_array[0] ;

	exec("find /lib/modules/ | grep -c usbip-host ", $exists_usbip_host_array) ;
	$exists_usbip_host = $exists_usbip_host_array[0] ;

	exec("find /lib/modules/ | grep -c vhci-hcd ", $exists_vhci_hcd_array) ;
	$exists_vhci_hcd = $exists_vhci_hcd_array[0] ;

}


function get_usbip_devs() {
	global $disks;

	$ud_disks = $paths = $unraid_disks = $b =  array();
	/* Get all devices by id. */
	
	$flash=&$disks['flash'] ;
	$flash_udev=array() ;
	exec('udevadm info --query=property  -n /dev/'.$flash["device"], $fudev) ;
	foreach ($fudev as $udevi)
	{
		$udevisplit=explode("=",$udevi) ;
		$flash_udev[$udevisplit[0]] = $udevisplit[1] ;
	}
	
	
	exec('usbip list -pl | sort'  ,$usbiplocal) ;
	/* Build USB Device Array */
	foreach ($usbiplocal as $usbip) {
		$usbipdetail=explode('#', $usbip) ;
		$busid=substr($usbipdetail[0] , 6) ;
		


		/* Build array from udevadm */
		/* udevadm info --query=property -x --path=/sys/bus/usb/devices/ + busid */

		exec('udevadm info --query=property  --path=/sys/bus/usb/devices/'.$busid, $udev) ;
		#$tj = array();
		foreach ($udev as $udevi)
		{
			$udevisplit=explode("=",$udevi) ;
			$tj[$busid][$udevisplit[0]] = $udevisplit[1] ;
		}
		
		$flash_check= $tj[$busid];
		if ($flash_check["ID_SERIAL_SHORT"] == $flash_udev["ID_SERIAL_SHORT"]) {
			$tj[$busid]["isflash"] = true ;
		}
		else { 
			$tj[$busid]["isflash"] = false ;
		}
	}
	return $tj ;
}

function get_all_usb_info($bus="all") {

	usbip_log("Starting get_all_usb_info.", "DEBUG");
	$time = -microtime(true);
	$usb_devs = get_usbip_devs();
	if (!is_array($usb_devs)) {
		$usb_devs = array();
	}
	usbip_log("Total time: ".($time + microtime(true))."s!", "DEBUG");

	return $usb_devs;
}


function parse_usbip_port()
{
	exec('usbip port', $cmd_return) ;

	$port_number = 0 ;
    $ports=array() ;
	foreach ($cmd_return as $line) {
		if ($line == "Imported USB devices") continue ;
		if ($line == "====================" ) continue ;
		if ($line == NULL) continue ;
		
		if (substr($line,0,4) == "Port") $port_num = substr($line, 5 ,2) ;
		$ports[$port_num][]=$line ;

	}
	
	return $ports ;
}
function parse_usbip_remote($remote_host)
{
	$usbip_cmd_list="usbip list -r ".$remote_host ;
	$cmd_return ="" ;
	$error=exec($usbip_cmd_list.' 2>&1', $cmd_return, $return) ;
	$count=0 ;
	$remotes=array() ;


	
	if ($return  || $error != "") {
		if ($error == false) {
			$error_type="USBIP command not found";
		} else {
			$error_type=$error;
		}
		$remotes[$remote_host]["NONE"]["detail"][] = "Connection Error" ;
		$remotes[$remote_host]["NONE"]["vendor"]=$error_type;
	
		$remotes[$remote_host]["NONE"]["product"]="";
		$remotes[$remote_host]["NONE"]["command"]=$error;
		$remotes[$remote_host]["NONE"]["return"]=$return;	
		$remotes[$remote_host]["NONE"]["cmdreturn"]=$cmd_return;
		$remotes[$remote_host]["NONE"]["error"]=$error;
	}

	foreach ($cmd_return as $line) {
		if ($line == "Exportable USB devices") continue ;
		if ($line == "======================" ) continue ;
		if ($line == NULL) {$count=2;continue ;}

		if (substr($line, 0, 12) == "usbip: error")  $remote[$remote_host]["NONE"] = $line ;

		if (substr($line, 1, 1) == '-') { 
			$usbip_ip= substr($line, 3) ;
			$count=1 ;

		}

		if ($count==2)
		       { 
				   $extract=explode(":", $line) ;
				   $busid=$extract[0] ;	
				   
				   $remotes[$usbip_ip][$busid]["vendor"]=$extract[1];
				   $remotes[$usbip_ip][$busid]["product"]=$extract[2].$extract[3];
			   }
		if 	   ($count>2) $remotes[$usbip_ip][$busid]["detail"][] = $line ;
		$count=$count+1 ;
	}
		
	return $remotes ;
}
?>
