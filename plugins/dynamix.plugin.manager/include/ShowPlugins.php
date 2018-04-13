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
require_once "$docroot/webGui/include/Markdown.php";
require_once "$docroot/plugins/dynamix.plugin.manager/include/PluginHelpers.php";

$system   = $_GET['system'] ?? false;
$branch   = $_GET['branch'] ?? false;
$source   = $_GET['source'] ?? false;
$audit    = $_GET['audit'] ?? false;
$check    = $_GET['check'] ?? false;
$release  = $_GET['release'] ?? false;
$empty    = true;
$updates  = 0;
$missing  = "None";
$builtin  = "unRAIDServer";
$plugins  = $system ? "/var/log/plugins/$builtin.plg" : "/var/log/plugins/*.plg";
$limetech = "https://s3.amazonaws.com/dnld.lime-technology.com";

if ($audit) {
  list($plg,$action) = explode(':',$audit);
  switch ($action) {
    case 'return' : $check = true; break;
    case 'remove' : return;
    case 'install':
    case 'update' : $plugins = "/var/log/plugins/$plg.plg"; break;
  }
}

function strip($name) {
  return str_replace('unRAID ','',$name);
}

function file_date($file) {
  //file is considered outdated when older than 1 day
  return file_exists($file) ? (filectime($file) > (time()-86400) ? 'up-to-date' : 'outdated') : 'unknown';
}

foreach (glob($plugins,GLOB_NOSORT) as $plugin_link) {
//only consider symlinks
  $plugin_file = @readlink($plugin_link);
  if ($plugin_file === false) continue;
//plugin name
  $name = plugin('name',$plugin_file) ?: basename($plugin_file,".plg");
//upgrade or downgrade selected release
  if ($release) {
    extract(parse_ini_file('/etc/unraid-version'));
    $tmp_file = "/var/tmp/$name.plg";
    if ($release != $version) {
      exec("sed -ri 's|^(<!ENTITY version).*|\\1   \"$release\">|' $tmp_file");
      echo "$plugin_file\0$tmp_file";
    } else {
      echo "$tmp_file\0$plugin_file";
    }
    return;
  }
//skip system when doing user plugins
  if (!$system && strpos($name,$builtin)===0) continue;
//forced plugin check?
  $forced = !$audit && !$check;
  $checked = $forced ? check_plugin(basename($plugin_file)) : true;
//switch stable/next/test release?
  if ($system) {
    //current version
    extract(parse_ini_file('/etc/unraid-version'));
    //category
    $category = $branch ?: plugin('category',$plugin_file) ?: (strpos($version,'-')===false ? 'stable' : 'next');
    if (!$branch && !$source) $source = $category;
    $releases = [];
    exec("curl -m 15 $limetech/$category/releases.json 2>/dev/null", $releases);
    if ($releases) $releases = json_decode(implode("\n",$releases),true); else $releases[] = ['name' => $missing];
    $release = strip($releases[0]['name']);
    if ($release != $missing) {
      $tmp_plg = "$name-.plg";
      $tmp_file = "/var/tmp/$name.plg";
      copy($plugin_file,$tmp_file);
      if ($branch) exec("sed -ri 's|^(<!ENTITY category).*|\\1  \"$branch\">|' $tmp_file");
      symlink($tmp_file,"/var/log/plugins/$tmp_plg");
      if ($release != $version && $branch) {
        if (check_plugin($tmp_plg)) copy("/tmp/plugins/$tmp_plg", $tmp_file);
        exec("sed -ri 's|^(<!ENTITY version).*|\\1   \"$release\">|' $tmp_file");
        $plugin_file = $tmp_file;
      }
    }
  } else {
    //plugin version
    $version = plugin('version',$plugin_file) ?: 'unknown';
  }
  $save = $version;
//link/icon
  $icon = icon($name);
  if ($launch = plugin('launch',$plugin_file))
    $link = "<a href='/$launch'><img src='/$icon' class='list'></a>";
  else
    $link = "<img src='/$icon' class='list'>";
//description
  $readme = "plugins/{$name}/README.md";
  if (file_exists($readme))
    $desc = Markdown(file_get_contents($readme));
  else
    $desc = Markdown("**{$name}**");
//author
  $author = plugin('author',$plugin_file) ?: 'anonymous';
//status
  $changes_file = $plugin_file;
  $url = plugin('pluginURL',$plugin_file);
  if ($url !== false) {
    $extra = $branch && $branch != $source;
    $filename = "/tmp/plugins/".(($system && $extra) ? $tmp_plg : basename($url));
    $latest = ($checked && file_exists($filename)) ? plugin('version',$filename) : 0;
    if ($system) {
      $release = strip($releases[0]['name']);
      $other = ($release != $missing);
      if ($extra) {
        if ($other) $version .= "<br><span id='os-release' class='red-text'>$release</span>";
        $status = "<span id='os-install'>".make_link('install', $plugin_file,$other?'forced':'disabled')."</span>";
      } else {
        $style1 = $style2 = "";
        if ($forced && $other && $latest===0) $latest = $release;
        if (version_compare($latest,$version,'>')) {
          $style1 = "style='display:none'";
          $version .= "<br><span id='os-release' class='red-text'>$latest</span>";
          $changes_file = $filename;
        } else {
          $style2 = "style='display:none'";
        }
        $status = "<span id='os-status' $style1>".file_date($filename)."</span>";
        $status .= "<span id='os-upgrade' $style2>".make_link('update', $plugin_file, null, 'upgrade')."</span>";
        $status .= "<span id='os-downgrade' style='display:none'>".make_link('install', $plugin_file, 'forced', 'downgrade')."</span>";
      }
    } else {
      if (strcmp($latest,$version) > 0) {
        $version .= "<br><span class='red-text'>$latest</span>";
        $status = make_link('update', basename($plugin_file), null, 'upgrade');
        $changes_file = $filename;
        $updates++;
      } else {
        $status = file_date($filename);
      }
    }
  }
  $changes = strpos($version,$missing)===false ? plugin('changes',$changes_file) : false;
  if ($changes !== false) {
    $txtfile = "/tmp/plugins/".basename($plugin_file,'.plg').".txt";
    file_put_contents($txtfile,$changes);
    $version .= "<i class='fa fa-info-circle fa-fw big blue-text' style='cursor:pointer' title='View Release Notes' ";
    $version .= "onclick=\"openBox('/plugins/dynamix.plugin.manager/include/ShowChanges.php?file=".urlencode($txtfile)."','Release Notes',600,900)\"></i>";
  }
//write plugin information
  $empty = false;
  echo "<tr id=\"".str_replace(['.',' ','_'],'',basename($plugin_file,'.plg'))."\">";
  echo "<td style='vertical-align:top;width:64px'><p style='text-align:center'>$link</p></td>";
  echo "<td><span class='desc_readmore' style='display:block'>$desc</span></td>";
  echo "<td>$author</td>";
  echo "<td data='$save'>$version</td>";
  if ($system) {
    // available releases
    $branch = $branch ?: $source;
    echo "<td><select id='change-version' class='narrow' onchange='change_version(\"$save\",this.value,\"$branch\")'>";
    if ($latest===0)
      echo mk_options(0,$missing);
    else
      foreach ($releases as $release) echo mk_options($version, strip($release['name']));
    echo "</select></td>";
    $rank = 0;
  } else {
    if (strpos($status,'upgrade')!==false) $rank = 0;
    elseif ($status=='outdated') $rank = 1;
    elseif ($status=='up-to-date') $rank = 2;
    else $rank = 3;
  }
  echo "<td data='$rank'>$status</td>";
  echo "<td>";
  if ($system) {
    echo "<select id='change-branch' class='auto' onchange='change_branch(\"$source\",this.value)'>";
    echo mk_options($category,'stable');
    echo mk_options($category,'next');
    echo mk_options($category,'test');
    echo "</select>";
  } else {
    echo make_link('remove', basename($plugin_file));
  }
  echo "</td>";
  echo "</tr>";
//remove temporary symlink
  if ($tmp_plg) unlink("/var/log/plugins/$tmp_plg");
}
if ($empty) echo "<tr><td colspan='6' style='text-align:center;padding-top:12px'><i class='fa fa-fw fa-check-square-o'></i> No plugins installed</td><tr>";
echo "\0".$updates;
?>
