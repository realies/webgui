<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2012-2018, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$device = $_POST['device'];
$name   = $_POST['name'];
$action = $_POST['action'];
$var    = parse_ini_file('state/var.ini');
$csrf   = $var['csrf_token'];

function emhttpd($cmd) {
  global $csrf;
  $ch = curl_init("http://127.0.0.1/update.htm?$cmd&csrf_token=$csrf");
  curl_setopt_array($ch, [CURLOPT_UNIX_SOCKET_PATH => '/var/run/emhttpd.socket', CURLOPT_RETURNTRANSFER => true]);
  curl_exec($ch);
  curl_close($ch);
}

if ($device=='New') {
  $cmd  = $action=='up' ? 'S0' : ($action=='down' ? 'y' : false);
  if ($cmd && $name) exec("/usr/sbin/hdparm -$cmd /dev/$name >/dev/null 2>&1");
} else {
  $disks = parse_ini_file('state/disks.ini',true);
  if ($name) emhttpd("cmdSpin$action=$name"); else emhttpd("cmdSpin{$device}All=true");
}
?>
