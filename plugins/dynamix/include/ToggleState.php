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

if ($device=='New') {
  $cmd  = $action=='up' ? 'S0' : ($action=='down' ? 'y' : false);
  if ($cmd && $name) exec("/usr/sbin/hdparm -$cmd /dev/$name >/dev/null 2>&1");
} else {
  $disks = parse_ini_file('state/disks.ini',true);
  if ($name) {
    exec("/usr/local/sbin/mdcmd spin$action {$disks[$name]['idx']} >/dev/null 2>&1");
  } else {
    foreach ($disks as $disk) exec("/usr/local/sbin/mdcmd spin$device {$disk['idx']} >/dev/null 2>&1");
  }
}
sleep(3); // delay for completion
?>
