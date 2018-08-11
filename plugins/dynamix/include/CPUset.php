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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$cpus = explode(';',$_POST['cpus']);

function create($id, $name, $vcpu) {
  // create the list of checkboxes. Make multiple rows when CPU cores are many ;)
  global $cpus;
  $total = count($cpus);
  $loop = floor(($total-1)/32)+1;
  $text = [];
  $unit = str_replace([' ','(',')','[',']'],'',$name);
  $name = urlencode($name);
  echo "<td><span id='$id-$unit' style='color:#267CA8;display:none'><i class='fa fa-refresh fa-spin'></i> updating</span></td>";
  for ($c = 0; $c < $loop; $c++) {
    $max = ($c == $loop-1 ? ($total%32?:32) : 32);
    for ($n = 0; $n < $max; $n++) {
      unset($cpu1,$cpu2);
      list($cpu1, $cpu2) = preg_split('/[,-]/',$cpus[$c*32+$n]);
      $check1 = in_array($cpu1, $vcpu) ? 'checked':'';
      $check2 = $cpu2 ? (in_array($cpu2, $vcpu) ? 'checked':''):'';
      $text[$n] .="<input type='checkbox' name='$name:$cpu1' $check1><br>";
      if ($cpu2) $text[$n] .= "<input type='checkbox' name='$name:$cpu2' $check2><br>";
    }
  }
  echo implode(array_map(function($t){return "<td>$t</td>";},$text));
}

switch ($_POST['id']) {
case 'vm':
  // create the current vm assignments
  require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
  $vms = $libvirt_running=='yes' ? $lv->get_domains() : [];
  $user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
  // list vms per user preference
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$vms);
  } else {
    natcasesort($vms);
  }
  foreach ($vms as $vm) {
    $uuid = $lv->domain_get_uuid($lv->get_domain_by_name($vm));
    $cfg = domain_to_config($uuid);
    echo "<tr><td>$vm</td>";
    create('vm', $vm, $cfg['domain']['vcpu']);
    echo "</tr>";
  }
  // return the cpu assignments and available VM names
  echo "\0".implode(';',array_map('urlencode',$vms));
  break;
case 'ct':
  // create the current container assignments
  require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
  $DockerClient = new DockerClient();
  $containers = $DockerClient->getDockerContainers();
  $user_prefs = $dockerManPaths['user-prefs'];
  $cts = []; foreach ($containers as $ct) $cts[] = $ct['Name'];
  // list containers per user preference
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($containers as $ct) $sort[] = array_search($ct['Name'],$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$containers);
    unset($sort);
  }
  foreach ($containers as $ct) {
    echo "<tr><td>{$ct['Name']}</td>";
    create('ct', $ct['Name'], explode(',',$ct['CPUset']));
    echo "</tr>";
  }
  // return the cpu assignments and available container names
  echo "\0".implode(';',array_map('urlencode',$cts));
  break;
}
?>
