#!/usr/bin/php
<?php

// NO NEED TO EDIT BELOW HERE!
require_once(__DIR__."/lib/fritzbox_api.class.php");
require_once(__DIR__."/lib/lua_parser.class.php");
require_once(__DIR__."/lib/sbLib.php");

$debug = false;

$fritz = new fritzbox_api();
$params = array('getpage'         => '/net/network_user_devices.lua');
$output = $fritz->doGetRequest($params);

$fritz = null;

$pattern='/.*/';

$lanKey = "landevice:settings/landevice/list(name,ip,mac,UID,dhcp,wlan,ethernet,active,static_dhcp,manu_name,wakeup,deleteable,source,online,speed,wlan_UIDs,auto_wakeup,guest,url,wlan_station_type,vendorname,parentname,parentuid,ethernet_port,wlan_show_in_monitor,plc,ipv6_ifid,parental_control_abuse)";
$wlanKey = "wlan:settings/wlanlist/list(hostname,mac,UID,state,rssi,quality,is_turbo,wmm_active,cipher,powersave,is_repeater,flags,flags_set,mode,is_guest,speed_rx,channel_width,streams)";

$dat = formatLua($output);

function cmpData($a, $b) {
  return $b["active"] - $a["active"];
}


usort($dat["MQUERIES"][$lanKey], "cmpData");

foreach($dat["MQUERIES"][$lanKey] as $k => $v) {
	printf("%s: %s (%s / %s) => %s\n", $v["wlan"] == 1 ? "WLAN" : " LAN", $v["name"], $v["mac"], $v["ip"], $v["active"]);
}
