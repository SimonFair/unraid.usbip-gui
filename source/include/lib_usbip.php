<?php
/* Copyright 2015, Guilherme Jardim
 * Copyright 2016-2020, Dan Landon
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

$paths = [  "smb_extra"			=> "/tmp/{$plugin}/smb-settings.conf",
			"smb_usb_shares"	=> "/etc/samba/unassigned-shares",
			"usb_mountpoint"	=> "/mnt/disks",
			"remote_mountpoint"	=> "/mnt/remotes",
			"device_log"		=> "/tmp/{$plugin}/",
			"config_file"		=> "/tmp/{$plugin}/config/{$plugin}.cfg",
			"state"				=> "/var/state/{$plugin}/{$plugin}.ini",
			"mounted"			=> "/var/state/{$plugin}/{$plugin}.json",
			"hdd_temp"			=> "/var/state/{$plugin}/hdd_temp.json",
			"run_status"		=> "/var/state/{$plugin}/run_status.json",
			"ping_status"		=> "/var/state/{$plugin}/ping_status.json",
			"df_status"			=> "/var/state/{$plugin}/df_status.json",
			"dev_status"		=> "/var/state/{$plugin}/devs_status.json",
			"hotplug_status"	=> "/var/state/{$plugin}/hotplug_status.json",
			"dev_state"			=> "/usr/local/emhttp/state/devs.ini",
			"samba_mount"		=> "/tmp/{$plugin}/config/samba_mount.cfg",
			"iso_mount"			=> "/tmp/{$plugin}/config/iso_mount.cfg",
			"remote_usbip"		=> "/tmp/{$plugin}/config/remote_usbip.cfg",
			"reload"			=> "/var/state/{$plugin}/reload.state",
			"unmounting"		=> "/var/state/{$plugin}/unmounting_%s.state",
			"mounting"			=> "/var/state/{$plugin}/mounting_%s.state",
			"formatting"		=> "/var/state/{$plugin}/formatting_%s.state",
			"scripts"			=> "/tmp/{$plugin}/scripts/",
			"credentials"		=> "/tmp/{$plugin}/credentials",
			"authentication"	=> "/tmp/{$plugin}/authentication",
			"luks_pass"			=> "/tmp/{$plugin}/luks_pass",
			"script_run"		=> "/tmp/{$plugin}/script_run"
		];

$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$users = @parse_ini_file("$docroot/state/users.ini", true);
$disks = @parse_ini_file("$docroot/state/disks.ini", true);

if (! isset($var)){
	if (! is_file("$docroot/state/var.ini")) shell_exec("/usr/bin/wget -qO /dev/null localhost:$(ss -napt | /bin/grep emhttp | /bin/grep -Po ':\K\d+') >/dev/null");
	$var = @parse_ini_file("$docroot/state/var.ini");
}

if ((! isset($var['USE_NETBIOS']) || ((isset($var['USE_NETBIOS'])) && ($var['USE_NETBIOS'] == "yes")))) {
	$use_netbios = "yes";
} else {
	$use_netbios = "no";
}

if ( is_file( "plugins/preclear.disk/assets/lib.php" ) )
{
	require_once( "plugins/preclear.disk/assets/lib.php" );
	$Preclear = new Preclear;
}
else
{
	$Preclear = null;
}

#########################################################
#############        MISC FUNCTIONS        ##############
#########################################################

class MiscUD
{

	public function save_json($file, $content)
	{
		file_put_contents($file, json_encode($content, JSON_PRETTY_PRINT ));
	}

	public function get_json($file)
	{
		return file_exists($file) ? @json_decode(file_get_contents($file), true) : [];
	}

	public function disk_device($disk)
	{
		return (file_exists($disk)) ? $disk : "/dev/{$disk}";
	}

	public function disk_name($disk)
	{
		return (file_exists($disk)) ? basename($disk) : $disk;
	}

	public function array_first_element($arr)
	{
		return (is_array($arr) && count($arr)) ? $arr[0] : $arr;
	}
}

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

function unassigned_log($m, $type = "NOTICE") {
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

function get_device_stats($mountpoint, $active=TRUE) {
	global $paths, $plugin;

	$tc = $paths['df_status'];
	$df_status = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
	$rc = "";
	if (is_mounted($mountpoint, TRUE)) {
		if (isset($df_status[$mountpoint])) {
			$rc = $df_status[$mountpoint]['stats'];
		}
		if (($active) && ((time() - $df_status[$mountpoint]['timestamp']) > 90) ) {
			exec("/usr/local/emhttp/plugins/{$plugin}/scripts/get_ud_stats df_status {$tc} '{$mountpoint}' &");
		}
	}
	return preg_split('/\s+/', $rc);
}

function get_disk_dev($dev) {
	global $paths;

	$rc		= basename($dev);
	$sf		= $paths['dev_state'];
	if (is_file($sf)) {
		$devs = parse_ini_file($paths['dev_state'], true);
		foreach ($devs as $d) {
			if (($d['device'] == basename($dev)) && isset($d['name'])) {
				$rc = $d['name'];
				break;
			}
		}
	}
	return $rc;
}

function get_disk_reads_writes($dev) {
	global $paths;

	$dev	= (strpos($dev, "nvme") !== false) ? preg_replace("#\d+p#i", "", $dev) : preg_replace("#\d+#i", "", $dev) ;
	$rc		= array();
	$sf		= $paths['dev_state'];
	if (is_file($sf)) {
		$devs = parse_ini_file($paths['dev_state'], true);
		foreach ($devs as $d) {
			if (($d['device'] == basename($dev)) && isset($d['numReads']) && isset($d['numWrites'])) {
				$rc[] = $d['numReads'];
				$rc[] = $d['numWrites'];
				break;
			}
		}
	}
	return $rc;
}

function is_disk_running($dev) {
	global $paths;

	$rc			= FALSE;
	$run_devs	= FALSE;
	$sf = $paths['dev_state'];
	if (is_file($sf)) {
		$devs = parse_ini_file($sf, true);
		foreach ($devs as $d) {
			if (($d['device'] == basename($dev)) && isset($d['spundown'])) {
				$rc =($d['spundown'] == '0') ? TRUE : FALSE;
				$run_devs = TRUE;
				break;
			}
		}
	}
	if (! $run_devs) {
		$tc = $paths['run_status'];
		$run_status = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
		if (isset($run_status[$dev]) && (time() - $run_status[$dev]['timestamp']) < 60) {
			$rc = ($run_status[$dev]['running'] == 'yes') ? TRUE : FALSE;
		} else {
			$state = trim(timed_exec(10, "/usr/sbin/hdparm -C $dev 2>/dev/null | /bin/grep -c standby"));
			$rc = ($state == 0) ? TRUE : FALSE;
			$run_status[$dev] = array('timestamp' => time(), 'running' => $rc ? 'yes' : 'no');
			file_put_contents($tc, json_encode($run_status));
		}
	}
	return $rc;
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



function get_temp($dev, $running) {
	global $var, $paths;

	$rc	= "*";
	if ($running) {
		$temp = "";
		$sf = $paths['dev_state'];
		if (is_file($sf)) {
			$devs = parse_ini_file($paths['dev_state'], true);
			foreach ($devs as $d) {
				if (($d['device'] == basename($dev)) && isset($d['temp'])) {
					$temp = $d['temp'];
					$rc = $temp;
					break;
				}
			}
		}
		if ($temp == "") {
			$tc = $paths['hdd_temp'];
			$temps = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
			if (isset($temps[$dev]) && (time() - $temps[$dev]['timestamp']) < $var['poll_attributes'] ) {
				$rc = $temps[$dev]['temp'];
			} else {
				$cmd	= "/usr/sbin/smartctl -n standby -A $dev | /bin/awk 'BEGIN{t=\"*\"} $1==\"Temperature:\"{t=$2;exit};$1==190||$1==194{t=$10;exit} END{print t}'";
				$temp	= trim(timed_exec(10, $cmd));
				$temp	= ($temp < 128) ? $temp : "*";
				$temps[$dev] = array('timestamp' => time(), 'temp' => $temp);
				file_put_contents($tc, json_encode($temps));
				$rc = $temp;
			}
		}
	}
	return $rc;
}

function benchmark() {
	$params   = func_get_args();
	$function = $params[0];
	array_shift($params);
	$time     = -microtime(true); 
	$out      = call_user_func_array($function, $params);
	$time    += microtime(true); 
	$type     = ($time > 10) ? "INFO" : "DEBUG";
	unassigned_log("benchmark: $function(".implode(",", $params).") took ".sprintf('%f', $time)."s.", $type);
	return $out;
}

function timed_exec($timeout=10, $cmd) {
	$time		= -microtime(true); 
	$out		= shell_exec("/usr/bin/timeout ".$timeout." ".$cmd);
	$time		+= microtime(true);
	if ($time >= $timeout) {
		unassigned_log("Error: shell_exec(".$cmd.") took longer than ".sprintf('%d', $timeout)."s!");
		$out	= "command timed out";
	} else {
		unassigned_log("Timed Exec: shell_exec(".$cmd.") took ".sprintf('%f', $time)."s!", "DEBUG");
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

function execute_script($info, $action, $testing = FALSE) { 
global $paths;

	putenv("ACTION={$action}");
	foreach ($info as $key => $value) {
		putenv(strtoupper($key)."={$value}");
	}
	$cmd = $info['command'];
	$bg = ($info['command_bg'] == "true" && $action == "ADD") ? "&" : "";
	if ($common_cmd = get_config("Config", "common_cmd")) {
		$common_script = $paths['scripts'].basename($common_cmd);
		copy($common_cmd, $common_script);
		@chmod($common_script, 0755);
		unassigned_log("Running common script: '".basename($common_script)."'");
		exec($common_script, $out, $return);
		if ($return) {
			unassigned_log("Error: common script failed with return '{$return}'");
		}
	}

	if ($cmd) {
		$command_script = $paths['scripts'].basename($cmd);
		copy($cmd, $command_script);
		@chmod($command_script, 0755);
		unassigned_log("Running device script: '".basename($cmd)."' with action '{$action}'.");

		$script_running = is_script_running($cmd);
		if ((! $script_running) || (($script_running) && ($action != "ADD"))) {
			if (! $testing) {
				if ($action == "REMOVE" || $action == "ERROR_UNMOUNT") {
					sleep(1);
				}
				$cmd = isset($info['serial']) ? "$command_script > /tmp/{$info['serial']}.log 2>&1 $bg" : "$command_script > /tmp/".preg_replace('~[^\w]~i', '', $info['device']).".log 2>&1 $bg";

				/* Set state as script running. */
				$script_name = $info['command'];
				$tc = $paths['script_run'];
				$script_run = is_file($tc) ? json_decode(file_get_contents($tc),TRUE) : array();
				$script_run[$script_name] = array('running' => 'yes','user' => 'no');
				file_put_contents($tc, json_encode($script_run));

				/* Run the script. */
				exec($cmd, $out, $return);
				if ($return) {
					unassigned_log("Error: device script failed with return '{$return}'");
				}
			} else {
				return $command_script;
			}
		} else {
			unassigned_log("Device script '".basename($cmd)."' aleady running!");
		}
	}

	return FALSE;
}

function remove_config_disk($sn) {

	$config_file = $GLOBALS["paths"]["config_file"];
	$config = @parse_ini_file($config_file, true);
	if ( isset($config[$source]) ) {
		unassigned_log("Removing configuration '$source'.");
	}
	$command = $config[$source]['command'];
	if ( isset($command) && is_file($command) ) {
		@unlink($command);
		unassigned_log("Removing script '$command'.");
	}
	unset($config[$sn]);
	save_ini_file($config_file, $config);
	return (! isset($config[$sn])) ? TRUE : FALSE;
}

function is_disk_ssd($device) {

	$rc		= FALSE;
	$device	= (strpos($device, "nvme") !== false) ? preg_replace("#\d+p#i", "", $device) : preg_replace("#\d+#i", "", $device) ;
	if (strpos($device, "nvme") === false) {
		$file = "/sys/block/".basename($device)."/queue/rotational";
		if (is_file($file)) {
			$rc = (@file_get_contents($file) == 0) ? TRUE : FALSE;
		} else {
			unassigned_log("Warning: Can't get rotational setting of '{$device}'.");
		}
	} else {
		$rc = TRUE;
	}
	return $rc;
}

function spin_disk($down, $dev) {
	if ($down) {
		exec("/usr/local/sbin/emcmd cmdSpindown=$dev");
	} else {
		exec("/usr/local/sbin/emcmd cmdSpinup=$dev");
	}
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
		unassigned_log("Error: unable to get the remote usbip hosts.");
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
		unassigned_log("Removing configuration '$source'.");
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
	global $loaded_usbip_host, $loaded_vhci_hcd, $usbip_cmds_exist ;

	exec("ls /usr/local/sbin/. | grep -c usbip*", $usbip_cmds_exist_array) ;
	$usbip_cmds_exist = $usbip_cmds_exist_array[0] ;
	
	exec("cat /proc/modules | grep -c vhci_hcd", $loaded_vhci_hcd_array) ;
	$loaded_vhci_hcd = $loaded_vhci_hcd_array[0] ;
	
	exec("cat /proc/modules | grep -c usbip_host", $loaded_usbip_host_array) ;
	$loaded_usbip_host = $loaded_usbip_host_array[0] ;

}


function get_unassigned_usb() {
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
	#var_dump($flash_udev) ;
	
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
		
		#$tj[$busid]["udev"] = $fudev;
      
	}


	return $tj ;
}

function get_all_usb_info($bus="all") {

	unassigned_log("Starting get_all_disks_info.", "DEBUG");
	$time = -microtime(true);
	$ud_disks = get_unassigned_usb();
	if (!is_array($ud_disks)) {
		$ud_disks = array();
	}
	unassigned_log("Total time: ".($time + microtime(true))."s!", "DEBUG");
/*	usort($ud_disks, create_function('$a, $b','$key="device";if ($a[$key] == $b[$key]) return 0; return ($a[$key] < $b[$key]) ? -1 : 1;')); */
	return $ud_disks;
}

function get_udev_info($device, $udev=NULL, $reload) {
	global $paths;

	$state = is_file($paths['state']) ? @parse_ini_file($paths['state'], true) : array();
	if ($udev) {
		$state[$device] = $udev;
		save_ini_file($paths['state'], $state);
		return $udev;
	} else if (array_key_exists($device, $state) && (! $reload)) {
		unassigned_log("Using udev cache for '$device'.", "DEBUG");
		return $state[$device];
	} else {
		$state[$device] = parse_ini_string(str_replace(array("$","!","\""), "", timed_exec(5,"/sbin/udevadm info --query=property --path $(/sbin/udevadm info -q path -n $device 2>/dev/null) 2>/dev/null")));
		save_ini_file($paths['state'], $state);
		unassigned_log("Not using udev cache for '$device'.", "DEBUG");
		return $state[$device];
	}
}



function parse_usbip_port()
{
	exec('usbip port', $cmd_return) ;
	#return $cmd_return ;
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
	ksort($remotes) ;
	return $remotes ;
}
?>
