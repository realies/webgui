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

$autostart_file = $dockerManPaths['autostart-file'];
$template_repos = $dockerManPaths['template-repos'];
$user_prefs = $dockerManPaths['user-prefs'];

// Update the start/stop configuration
if ($_POST['action'] == 'autostart' ){
  $json = ($_POST['response'] == 'json') ? true : false;

  if (!$json) readfile("$docroot/update.htm");

  $container = urldecode(($_POST['container']));
  unset($_POST['container']);

  $allAutoStart = @file($autostart_file, FILE_IGNORE_NEW_LINES) ?: [];
  $key = array_search($container, $allAutoStart);
  if ($key===false) {
    array_push($allAutoStart, $container);
    if ($json) echo json_encode(['autostart' => true]);
  } else {
    unset($allAutoStart[$key]);
    if ($json) echo json_encode(['autostart' => false]);
  }
  // sort containers for start-up
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($allAutoStart as $ct) $sort[] = array_search($ct,$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$allAutoStart);
  } else {
    natcasesort($allAutoStart);
  }
  $allAutoStart ? file_put_contents($autostart_file, implode(PHP_EOL, $allAutoStart).PHP_EOL) : @unlink($autostart_file);
}

if ($_POST['#action'] == 'templates' ){
  readfile("$docroot/update.htm");
  $repos = $_POST['template_repos'];
  file_put_contents($template_repos, $repos);
  $DockerTemplates = new DockerTemplates();
  $DockerTemplates->downloadTemplates();
}

if ( isset($_GET['is_dir'] )) {
  echo json_encode(['is_dir' => is_dir($_GET['is_dir'])]);
}
?>
