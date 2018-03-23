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
function docker($cmd) {
  return exec("timeout 20 docker $cmd 2>/dev/null");
}

exec("timeout 20 docker ps -a --format='{{.Names}}' 2>/dev/null",$all_containers);

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($all_containers as $ct) $sort[] = array_search($ct,$prefs) ?? 999;
  array_multisort($sort,SORT_NUMERIC,$all_containers);
}

$action = $_POST['action'];
switch ($action) {
  case 'stop' : $state = 'true'; $all_containers = array_reverse($all_containers); break;
  case 'start': $state = 'false'; break;
}

foreach ($all_containers as $ct) {
  if (docker("inspect --format='{{.State.Running}}' $ct")==$state) docker("$action $ct >/dev/null");
}
?>
