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
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";

$DockerClient    = new DockerClient();
$DockerTemplates = new DockerTemplates();
$containers      = $DockerClient->getDockerContainers();
$images          = $DockerClient->getDockerImages();
$user_prefs      = $dockerManPaths['user-prefs'];

if (!$containers && !$images) {
  echo "<tr><td colspan='8' style='text-align:center;padding-top:12px'>No Docker containers installed</td></tr>";
  return;
}

if (file_exists($user_prefs)) {
  $prefs = parse_ini_file($user_prefs); $sort = [];
  foreach ($containers as $ct) $sort[] = array_search($ct['Name'],$prefs) ?? 999;
  array_multisort($sort,SORT_NUMERIC,$containers);
  unset($sort);
}

// Read container info
$allInfo = $DockerTemplates->getAllInfo();
$docker = ['var docker=[];'];
$null = '0.0.0.0';
$menu = [];

foreach ($containers as $ct) {
  $name = $ct['Name'];
  $id = $ct['Id'];
  $info = &$allInfo[$name];
  $running = $info['running'] ? 1 : 0;
  $paused = $info['paused'] ? 1 : 0;
  $is_autostart = $info['autostart'] ? 'true':'false';
  $updateStatus = $info['updated']=='true'||$info['updated']=='undef' ? 'true':'false';
  $template = $info['template'];
  $shell = $info['shell'];
  $webGui = html_entity_decode($info['url']);
  $support = html_entity_decode($info['Support']);
  $project = html_entity_decode($info['Project']);
  $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,%s,'%s','%s','%s','%s','%s');", addslashes($name), addslashes($ct['ImageId']), addslashes($template), $running, $paused, $updateStatus, $is_autostart, addslashes($webGui), $shell, $id, addslashes($support), addslashes($project));
  $docker[] = "docker.push({name:'$name',id:'$id',state:$running,pause:$paused,update:'$updateStatus'});";
  $shape = $running ? ($paused ? 'pause' : 'play') : 'square';
  $status = $running ? ($paused ? 'paused' : 'started') : 'stopped';
  $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
  $ports = [];
  foreach ($ct['Ports'] as $port) {
    $intern = $running ? ($ct['NetworkMode']=='host' ? $host : $port['IP']) : $null;
    $extern = $running ? ($port['NAT'] ? $host : $intern) : $null;
    $ports[] = sprintf('%s:%s/%s<i class="fa fa-arrows-h" style="margin:0 6px"></i>%s:%s', $intern, $port['PrivatePort'], strtoupper($port['Type']), $extern, $port['PublicPort']);
  }
  $paths = [];
  foreach ($ct['Volumes'] as $mount) {
    list($host_path,$container_path,$access_mode) = explode(':',$mount);
    $paths[] = sprintf('%s<i class="fa fa-%s" style="margin:0 6px"></i>%s', htmlspecialchars($container_path), $access_mode=='ro'?'long-arrow-left':'arrows-h', htmlspecialchars($host_path));
  }
  echo "<tr class='sortable'><td style='width:48px;padding:4px'>";
  echo "<div id='$id' style='display:block; cursor:pointer'><div style='position:relative;width:48px;height:48px;margin:0px auto'>";
  echo "<img src='".htmlspecialchars($icon)."' class='$status' style='position:absolute;top:0;bottom:0;left:0;right:0;width:48px;height:48px'>";
  echo "<i class='fa iconstatus fa-$shape $status' title='$status'></i></div></div>";
  echo "</td><td class='ct-name'>";
  if ($template) {
    echo "<a class='exec' onclick=\"editContainer('".addslashes(htmlspecialchars($name))."','".addslashes(htmlspecialchars($template))."')\">".htmlspecialchars($name)."</a>";
  } else {
    echo htmlspecialchars($name);
  }
  echo "<div class='advanced' style='width:160px'>Container ID: $id</div>";
  if ($ct['BaseImage']) echo "<div class='advanced' style='width:160px;'><i class='fa fa-cubes' style='margin-right:5px'></i>".htmlspecialchars(${ct['BaseImage']})."</div>"; 
  echo "<div class='advanced' style='width:160px'>By:";
  $registry = $info['registry'];
  if ($registry) {
    echo "<a href='".htmlspecialchars($registry)."' target='_blank'>".htmlspecialchars($ct['Image'])."</a>";
  } else {
    echo htmlspecialchars($ct['Image']);
  }
  echo "</div></td><td class='updatecolumn'>";
  if ($updateStatus=='false') {
    echo "<a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> update ready</span></a>";
  } elseif ($updateStatus=='true') {
    echo "<span style='color:#44B012;white-space:nowrap;'><i class='fa fa-check'></i> up-to-date</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> force update</span></a></div>";
  } else {
    echo "<span style='color:#FF2400;white-space:nowrap;'><i class='fa fa-exclamation-triangle'></i> not available</span>";
    echo "<div class='advanced'><a class='exec' onclick=\"updateContainer('".addslashes(htmlspecialchars($name))."');\"><span style='white-space:nowrap;'><i class='fa fa-cloud-download'></i> force update</span></a></div>";
  }
  echo "</td><td>{$ct['NetworkMode']}</td>";
  echo "<td style='white-space:nowrap'><span class='docker_readmore'>".implode('<br>',$ports)."</span></td>";
  echo "<td style='word-break:break-all'><span class='docker_readmore'>".implode('<br>',$paths)."</span></td>";
  echo "<td><div class='usage-disk sys'><span id='cpu-$id' style='width:0'>-</span></div></td>";
  echo "<td><div class='usage-disk sys'><span id='mem-$id' style='width:0'>-</span></div></td>";
  echo "<td><input type='checkbox' class='autostart' container='".htmlspecialchars($name)."'".($info['autostart'] ? ' checked':'')."></td>";
  echo "<td><a class='log' onclick=\"containerLogs('".addslashes(htmlspecialchars($name))."','$id',false,false)\"><img class='basic' src='/plugins/dynamix/icons/log.png'><div class='advanced'>";
  echo htmlspecialchars(str_replace('Up','Uptime',$ct['Status']))."</div><div class='advanced' style='margin-top:4px'>Created ".htmlspecialchars($ct['Created'])."</div></a></td></tr>";
}
foreach ($images as $image) {
  if (count($image['usedBy'])) continue;
  $id = $image['Id'];
  $menu[] = sprintf("addDockerImageContext('%s','%s');", $id, implode(',',$image['Tags']));
  echo "<tr class='advanced'><td style='width:48px;padding:4px'>";
  echo "<div id='$id' style='display:block;cursor:pointer'>";
  echo "<div style='position:relative;width:48px;height:48px;margin:0 auto'>";
  echo "<img src='/webGui/images/disk.png' style='position:absolute;opacity:0.3;top:0;bottom:0;left:0;right:0;width:48px;height:48px'>";
  echo "</div></div></td>";
  echo "<td><i>(orphan image)</i><div style='width:160px;'>Image ID: $id</div>";
  echo "<div style='width:160px'>".implode('<br>',array_map('htmlspecialchars',$image['Tags']))."</div></td>";
  echo "<td colspan='7'></td>";
  echo "<td><div class='advanced' style='width:124px'>Created ".htmlspecialchars($image['Created'])."</div></td></tr>";
}
echo "\0".implode($menu).implode($docker);
?>
