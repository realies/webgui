<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2014-2018, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
$user_prefs = '/boot/config/plugins/dockerMan/userprefs.cfg';

# controlled docker execution
function docker($cmd, &$var=null) {
  return exec("timeout 20 /usr/bin/docker $cmd 2>/dev/null",$var);
}
$action = $_POST['action'];
$status = $action=='start' ? 'exited' : 'running';
$all_containers=[]; docker("ps -a --filter status='$status' --format='{{.Names}}'", $all_containers);

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($all_containers as $ct) $sort[] = array_search($ct,$prefs) ?? 999;
  array_multisort($sort, ($action=='start'?SORT_ASC:SORT_DESC), SORT_NUMERIC, $all_containers);
}

foreach ($all_containers as $ct) docker("$action $ct >/dev/null");
?>
