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

$name = urldecode($_POST['name']);
switch ($_POST['id']) {
case 'vm':
  // update vm
  require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";
  require_once "$docroot/webGui/include/Helpers.php";
  $file = "/var/tmp/$name.tmp";
  if (file_exists($file)) {
    // read new cpu assignments
    $cpuset = explode(',',file_get_contents($file));
    unlink($file);
    // get vm reference
    $uuid = $lv->domain_get_uuid($lv->get_domain_by_name($name));
    $cfg = domain_to_config($uuid);
    // update cpu assignments
    $cfg['domain']['type'] = 'kvm';
    $cfg['domain']['vcpus'] = count($cpuset);
    $cfg['domain']['vcpu'] = $cpuset;
    // we need to do something with USB
    $build = [];
    foreach ($cfg['usb'] as $index => $usb) $build[] = $usb['id'];
    unset($cfg['usb']);
    foreach ($build as $usb) $cfg['usb'][] = $usb;
    // we need to do something with PCI
    $build = [];
    foreach ($cfg['pci'] as $index => $pci) $build[] = $pci['id'];
    unset($cfg['pci']);
    foreach ($build as $pci) $cfg['pci'][] = $pci;
    $dom = $lv->domain_get_domain_by_uuid($uuid);
    // autostart?
    $auto = $lv->domain_get_autostart($dom);
    // restore old in case of failure
    $old = $lv->domain_get_xml($dom);
    // stop running vm first?
    $running = $lv->domain_get_state($dom)=='running';
    if ($running) {
      $lv->domain_shutdown($dom);
      for ($n =0; $n < 30; $n++) { // allow up to 30s for VM to shutdown
        sleep(1);
        if ($stopped = $lv->domain_get_state($dom)=='shutoff') break;
      }
    } else $stopped = true;
    if ($stopped) {
      $lv->nvram_backup($uuid);
      $lv->domain_undefine($dom);
      $lv->nvram_restore($uuid);
      $new = $lv->domain_new($cfg);
      if ($new) {
        if ($running) {
          if (!$lv->domain_start($dom)) {
            $reply = ['error' => $lv->get_last_error()];
            break;
          }
        }
        $lv->domain_set_autostart($new, $auto==1);
        $reply = ['success' => $name];
      } else {
        $error = $lv->get_last_error();
        $new = $lv->domain_define($old);
        if ($new) $lv->domain_set_autostart($new, $auto==1);
        $reply = ['error' => $error];
      }
    } else {
      $reply = ['error' => "Failed to stop '$name'"];
    }
  } else {
    $reply = ['error' => "File: '$file' not found"];
  }
  break;
case 'ct':
  // update docker container
  require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
  require_once "$docroot/plugins/dynamix.docker.manager/include/Helpers.php";
  $DockerClient = new DockerClient();
  $DockerTemplates = new DockerTemplates();
  // get available networks
  $subnet = DockerUtil::network(DockerUtil::custom());
  // get full template path
  $xml = $DockerTemplates->getUserTemplate($name);
  list($cmd, $ct, $repository) = xmlToCommand($xml);
  $imageID = $DockerClient->getImageID($repository);
  // pull image
  $container = $DockerClient->getContainerDetails($ct);
  // determine if the container is still running
  if (!empty($container) && !empty($container['State']) && !empty($container['State']['Running'])) {
    // since container was already running, put it back it to a running state after update
    $cmd = str_replace('/docker create ', '/docker run -d ', $cmd);
    // attempt graceful stop of container first
    $DockerClient->stopContainer($ct,30);
  }
  // force kill container if still running after 30 seconds
  $DockerClient->removeContainer($ct);
  execCommand($cmd,false);
  $DockerClient->flushCaches();
  $newImageID = $DockerClient->getImageID($repository);
  // remove old orphan image since it's no longer used by this container
  if ($imageID && $imageID != $newImageID) {
    $DockerClient->removeImage($imageID);
  }
  $reply = ['success' => $name];
  break;
}
header('Content-Type: application/json');
die(json_encode($reply));
?>
