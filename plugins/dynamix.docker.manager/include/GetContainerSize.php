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
function autoscale($value) {
  $unit = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
  $size = count($unit);
  $base = $value ? floor(log($value, 1000)) : 0;
  if ($base>$size) $base = $size-1;
  $value /= pow(1000, $base);
  $decimals = $base ? ($value>=100 ? 0 : ($value>=10 ? 1 : (round($value*100)%100===0 ? 0 : 2))) : 0;
  return number_format($value, $decimals, '.', $value>9999 ? ',' : '').$unit[$base];
}

function align($text, $w=13) {
  if ($w>0) $text = preg_replace('/([kMGTPEZY]?B)/'," $1",$text);
  return sprintf("%{$w}s",$text);
}

exec("docker ps -sa --format='{{.Names}}|{{.Size}}'",$containers);
natcasesort($containers);
echo align('Name',-30).align('Container').align('Writable').align('Log')."\n";
echo str_repeat('-',69)."\n";
foreach ($containers as $container) {
  list($name,$size) = explode('|',$container);
  list($writable,$dummy,$total) = explode(' ',str_replace(['(',')'],'',$size));
  $log = autoscale(exec("docker logs $name 2>/dev/null|wc -c"));
  echo align($name,-30).align($total).align($writable).align($log)."\n";
}
?>
