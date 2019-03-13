<?PHP
/* Copyright 2005-2019, Lime Technology
 * Copyright 2012-2019, Bergware International.
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
switch ($_POST['#case']) {
case 'keypair':
  $private = '/var/tmp/privatekey';
  $public = '/var/tmp/publickey';
  exec("wg genkey|tee $private|wg pubkey >$public");
  echo @file_get_contents($private)."\0".@file_get_contents($public);
  @unlink($private);
  @unlink($public);
  break;
case 'update':
  $wg = $_POST['#wg'];
  $port = $_POST['#port'];
  $conf = ['[Interface]'];
  $n = 0;
  foreach ($_POST as $key => $value) {
    if ($key[0]=='#') continue;
    list($id,$i) = explode(':',$key);
    if ($i != $n) {$conf[] = "\n[Peer]"; $n = $i;}
    if ($value) $conf[] = "$id=$value";
  }
  file_put_contents($file,implode("\n",$conf));
  exec("wg-quick down $port 2>/dev/null");
  if ($wg) exec("wg-quick up $port 2>/dev/null");
  $save = false;
  break;
case 'toggle':
  $wg = $_POST['#wg'];
  $port = $_POST['#port'];
  if ($wg=='stop') exec("wg-quick down $port 2>/dev/null");
  if ($wg=='start') exec("wg-quick up $port 2>/dev/null");
  break;
}
?>