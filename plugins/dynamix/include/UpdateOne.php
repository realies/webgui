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
$map = $changes = [];
foreach (array_map('urldecode',explode(';',$_POST['names'])) as $name) $map[$name] = '';

foreach($_POST as $key => $val) {
  if ($val != 'on') continue;
  list($name,$cpu) = explode(':',$key);
  $map[urldecode($name)] .= "$cpu,";
}
// map holds the list of each vm or container and its newly proposed cpu assignments
$map = array_map(function($d){return substr($d,0,-1);},$map);

switch ($_POST['id']) {
case 'vm':
  // report changed vms in temporary file
  require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
  foreach ($map as $name => $cpuset) {
    $uuid = $lv->domain_get_uuid($lv->get_domain_by_name($name));
    $cfg = domain_to_config($uuid);
    $cpus = implode(',',$cfg['domain']['vcpu']);
    // only act on changes
    if ($cpus != $cpuset || strlen($cpus) != strlen($cpuset)) {
      $changes[] = $name;
      // used by UpdateTwo.php to read new assignments
      file_put_contents("/var/tmp/$name.tmp",$cpuset);
    }
  }
  break;
case 'ct':
  // update the XML file of the container
  require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
  $DockerClient = new DockerClient();
  $DockerTemplates = new DockerTemplates();
  $containers = $DockerClient->getDockerContainers();
  foreach ($map as $name => $cpuset) {
    // set full path of template file
    $file = $DockerTemplates->getUserTemplate($name);
    $xml = simplexml_load_file($file);
    if ($xml->CPUset) {
      // update node
      if ($xml->CPUset != $cpuset || strlen($xml->CPUset) != strlen($cpuset)) $xml->CPUset = $cpuset;
    } else {
      // add node
      $xml->addChild('CPUset',$cpuset);
    }
    // only act on changes
    foreach ($containers as $ct) if ($ct['Name']==$name) break;
    if ($ct['CPUset'] != $cpuset || strlen($ct['CPUset']) != strlen($cpuset)) {
      $changes[] = $name;
      // used by UpdateTwo.php to read new assignments
      file_put_contents($file,$xml->saveXML());
      exec("sed -ri 's/^(<CPUset)/  \\1/;s/><(\\/Container)/>\\n  <\\1/' \"$file\""); // aftercare
    }
  }
  break;
}
// signal changes
$reply = ['success' => (count($changes) ? implode(';',$changes) : '')];
header('Content-Type: application/json');
die(json_encode($reply));
?>
