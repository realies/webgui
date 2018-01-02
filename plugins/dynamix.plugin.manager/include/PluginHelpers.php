<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2012-2017, Bergware International.
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

// Invoke the plugin command with indicated method
function plugin($method, $arg = '') {
  global $docroot;
  exec("$docroot/plugins/dynamix.plugin.manager/scripts/plugin ".escapeshellarg($method)." ".escapeshellarg($arg), $output, $retval);
  return $retval==0 ? implode("\n", $output) : false;
}

function check_plugin($arg, $google='8.8.8.8') {
// ping google DNS server first to ensure internet is present
  $inet = exec("ping -qnl2 -c2 -W3 $google|awk '/received/{print $4}'");
  return $inet ? plugin('check',$arg) : false;
}

function make_link($method, $arg, $extra='') {
  $id = basename($arg, '.plg').$method;
  $check = $method=='remove' ? "<input type='checkbox' onClick='document.getElementById(\"$id\").disabled=!this.checked'>" : "";
  $disabled = $check ? ' disabled' : '';
  $cmd = $method == 'delete' ? "/plugins/dynamix.plugin.manager/scripts/plugin_rm&arg1=$arg" : "/plugins/dynamix.plugin.manager/scripts/plugin&arg1=$method&arg2=$arg".($extra?"&arg3=$extra":"");
  $clr = $method == 'delete' ? "" : "noAudit();";
  return "{$check}<input type='button' id='$id' value='".ucfirst($method)."' onclick='{$clr}openBox(\"{$cmd}\",\"".ucwords($method)." Plugin\",600,900,true)'{$disabled}>";
}

// trying our best to find an icon
function icon($name) {
// this should be the default location and name
  $icon = "plugins/{$name}/images/{$name}.png";
  if (file_exists($icon)) return $icon;
// try alternatives if default is not present
  $plugin = strtok($name, '.');
  $icon = "plugins/{$plugin}/images/{$plugin}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/images/{$name}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/{$plugin}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/{$name}.png";
  if (file_exists($icon)) return $icon;
// last resort - plugin manager icon
  return "plugins/dynamix.plugin.manager/images/dynamix.plugin.manager.png";
}
function mk_options($select,$value) {
  return "<option value='$value'".($select==$value?" selected":"").">".ucfirst($value)."</option>";
}
?>
