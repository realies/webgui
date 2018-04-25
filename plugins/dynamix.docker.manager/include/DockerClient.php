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

$dockerManPaths = [
	'autostart-file' => "/var/lib/docker/unraid-autostart",
	'update-status'  => "/var/lib/docker/unraid-update-status.json",
	'template-repos' => "/boot/config/plugins/dockerMan/template-repos",
	'templates-user' => "/boot/config/plugins/dockerMan/templates-user",
	'templates-usb'  => "/boot/config/plugins/dockerMan/templates",
	'images-usb'     => "/boot/config/plugins/dockerMan/images",
	'user-prefs'     => "/boot/config/plugins/dockerMan/userprefs.cfg",
	'plugin'         => "$docroot/plugins/dynamix.docker.manager",
	'images-ram'     => "$docroot/state/plugins/dynamix.docker.manager/images",
	'webui-info'     => "$docroot/state/plugins/dynamix.docker.manager/docker.json"
];

// load network variables if needed.
if (!isset($eth0) && is_file("$docroot/state/network.ini")) extract(parse_ini_file("$docroot/state/network.ini",true));
$host = $eth0['IPADDR:0'] ?? '0.0.0.0';

// Docker configuration file - guaranteed to exist
$docker_cfgfile = '/boot/config/docker.cfg';
$dockercfg = parse_ini_file($docker_cfgfile);

#######################################
##       DOCKERTEMPLATES CLASS       ##
#######################################

class DockerTemplates {
	public $verbose = false;

	private function debug($m) {
		if ($this->verbose) echo $m."\n";
	}

	private function removeDir($path) {
		if (is_dir($path)) {
			$files = array_diff(scandir($path), ['.', '..']);
			foreach ($files as $file) {
				$this->removeDir(realpath($path).'/'.$file);
			}
			return rmdir($path);
		} elseif (is_file($path)) return unlink($path);
		return false;
	}

	public function download_url($url, $path='', $bg=false) {
		exec('curl --max-time 60 --silent --insecure --location --fail '.($path ? ' -o '.escapeshellarg($path) : '').' '.escapeshellarg($url).' '.($bg ? '>/dev/null 2>&1 &' : '2>/dev/null'), $out, $exit_code);
		return $exit_code===0 ? implode("\n", $out) : false;
	}

	public function listDir($root, $ext=null) {
		$iter = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($root,
						RecursiveDirectoryIterator::SKIP_DOTS),
						RecursiveIteratorIterator::SELF_FIRST,
						RecursiveIteratorIterator::CATCH_GET_CHILD);
		$paths = [];
		foreach ($iter as $path => $fileinfo) {
			$fext = $fileinfo->getExtension();
			if ($ext && $ext != $fext) continue;
			if (substr(basename($path),0,1) == ".")  continue;
			if ($fileinfo->isFile()) $paths[] = ['path' => $path, 'prefix' => basename(dirname($path)), 'name' => $fileinfo->getBasename(".$fext")];
		}
		return $paths;
	}

	public function getTemplates($type) {
		global $dockerManPaths;
		$tmpls = $dirs = [];
		switch ($type) {
		case 'all':
			$dirs[] = $dockerManPaths['templates-user'];
			$dirs[] = $dockerManPaths['templates-usb'];
			break;
		case 'user':
			$dirs[] = $dockerManPaths['templates-user'];
			break;
		case 'default':
			$dirs[] = $dockerManPaths['templates-usb'];
			break;
		default:
			$dirs[] = $type;
		}
		foreach ($dirs as $dir) {
			if (!is_dir($dir)) @mkdir($dir, 0755, true);
			$tmpls = array_merge($tmpls, $this->listDir($dir, 'xml'));
		}
		array_multisort(array_column($tmpls,'name'), SORT_NATURAL|SORT_FLAG_CASE, $tmpls);
		return $tmpls;
	}

	public function downloadTemplates($Dest=null, $Urls=null) {
		global $dockerManPaths;
		$Dest = $Dest ?: $dockerManPaths['templates-usb'];
		$Urls = $Urls ?: $dockerManPaths['template-repos'];
		$repotemplates = $output = [];
		$tmp_dir = '/tmp/tmp-'.mt_rand();
		if (!file_exists($dockerManPaths['template-repos'])) {
			@mkdir(dirname($dockerManPaths['template-repos']), 0777, true);
			@file_put_contents($dockerManPaths['template-repos'], 'https://github.com/limetech/docker-templates');
		}
		$urls = @file($Urls, FILE_IGNORE_NEW_LINES);
		if (!is_array($urls)) return false;
		//$this->debug("\nURLs:\n   ".implode("\n   ", $urls));
		$github_api_regexes = [
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
			'%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)$%i',
			'%/.*github.com/([^/]*)/(.*).git%i',
			'%/.*github.com/([^/]*)/(.*)%i'
		];
		foreach ($urls as $url) {
			$github_api = ['url' => ''];
			foreach ($github_api_regexes as $api_regex) {
				if (preg_match($api_regex, $url, $matches)) {
					$github_api['user']   = $matches[1] ?? '';
					$github_api['repo']   = $matches[2] ?? '';
					$github_api['branch'] = $matches[3] ?? 'master';
					$github_api['path']   = $matches[4] ?? '';
					$github_api['url']    = sprintf('https://github.com/%s/%s/archive/%s.tar.gz', $github_api['user'], $github_api['repo'], $github_api['branch']);
					break;
				}
			}
			// if after above we don't have a valid url, check for GitLab
			if (empty($github_api['url'])) {
				$source = file_get_contents($url);
				// the following should always exist for GitLab Community Edition or GitLab Enterprise Edition
				if (preg_match("/<meta content='GitLab (Community|Enterprise) Edition' name='description'>/", $source) > 0) {
					$parse = parse_url($url);
					$custom_api_regexes = [
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
						'%/'.$parse['host'].'/([^/]*)/([^/]*)/tree/([^/]*)$%i',
						'%/'.$parse['host'].'/([^/]*)/(.*).git%i',
						'%/'.$parse['host'].'/([^/]*)/(.*)%i',
					];
					foreach ($custom_api_regexes as $api_regex) {
						if (preg_match($api_regex, $url, $matches)) {
							$github_api['user']   = $matches[1] ?? '';
							$github_api['repo']   = $matches[2] ?? '';
							$github_api['branch'] = $matches[3] ?? 'master';
							$github_api['path']   = $matches[4] ?? '';
							$github_api['url']    = sprintf('https://'.$parse['host'].'/%s/%s/repository/archive.tar.gz?ref=%s', $github_api['user'], $github_api['repo'], $github_api['branch']);
							break;
						}
					}
				}
			}
			if (empty($github_api['url'])) {
				//$this->debug("\n Cannot parse URL ".$url." for Templates.");
				continue;
			}
			if ($this->download_url($github_api['url'], "$tmp_dir.tar.gz") === false) {
				//$this->debug("\n Download ".$github_api['url']." has failed.");
				@unlink("$tmp_dir.tar.gz");
				return null;
			} else {
				@mkdir($tmp_dir, 0777, true);
				shell_exec("tar -zxf $tmp_dir.tar.gz --strip=1 -C $tmp_dir/ 2>&1");
				unlink("$tmp_dir.tar.gz");
			}
			$tmplsStor = [];
			//$this->debug("\n Templates found in ".$github_api['url']);
			foreach ($this->getTemplates($tmp_dir) as $template) {
				$storPath = sprintf('%s/%s', $Dest, str_replace($tmp_dir.'/', '', $template['path']));
				$tmplsStor[] = $storPath;
				if (!is_dir(dirname($storPath))) @mkdir(dirname($storPath), 0777, true);
				if (is_file($storPath)) {
					if (sha1_file($template['path']) === sha1_file($storPath)) {
						//$this->debug("   Skipped: ".$template['prefix'].'/'.$template['name']);
						continue;
					} else {
						@copy($template['path'], $storPath);
						//$this->debug("   Updated: ".$template['prefix'].'/'.$template['name']);
					}
				} else {
					@copy($template['path'], $storPath);
					//$this->debug("   Added: ".$template['prefix'].'/'.$template['name']);
				}
			}
			$repotemplates = array_merge($repotemplates, $tmplsStor);
			$output[$url] = $tmplsStor;
			$this->removeDir($tmp_dir);
		}
		// Delete any templates not in the repos
		foreach ($this->listDir($Dest, 'xml') as $arrLocalTemplate) {
			if (!in_array($arrLocalTemplate['path'], $repotemplates)) {
				unlink($arrLocalTemplate['path']);
				//$this->debug("   Removed: ".$arrLocalTemplate['prefix'].'/'.$arrLocalTemplate['name']."\n");
				// Any other files left in this template folder? if not delete the folder too
				$files = array_diff(scandir(dirname($arrLocalTemplate['path'])), ['.', '..']);
				if (empty($files)) {
					rmdir(dirname($arrLocalTemplate['path']));
					//$this->debug("   Removed: ".$arrLocalTemplate['prefix']);
				}
			}
		}
		return $output;
	}

	public function getTemplateValue($Repository, $field, $scope='all') {
		foreach ($this->getTemplates($scope) as $file) {
			$doc = new DOMDocument();
			$doc->load($file['path']);
			$TemplateRepository = DockerUtil::ensureImageTag($doc->getElementsByTagName('Repository')->item(0)->nodeValue);
			if ($Repository == $TemplateRepository) {
				$TemplateField = $doc->getElementsByTagName($field)->item(0)->nodeValue;
				return trim($TemplateField);
			}
		}
		return null;
	}

	public function getUserTemplate($Container) {
		foreach ($this->getTemplates('user') as $file) {
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->load($file['path']);
			$Name = $doc->getElementsByTagName('Name')->item(0)->nodeValue;
			if ($Name==$Container) return $file['path'];
		}
		return false;
	}

	private function getControlURL(&$ct, $myIP) {
		global $host;
		$port = &$ct['Ports'][0];
		$myIP = $myIP ?: $this->getTemplateValue($ct['Image'], 'MyIP') ?: ($ct['NetworkMode']=='host'||$port['NAT'] ? $host : ($port['IP'] ?: DockerUtil::myIP($ct['Name'])));
		$WebUI = preg_replace("%\[IP\]%", $myIP, $this->getTemplateValue($ct['Image'], 'WebUI'));
		if (preg_match("%\[PORT:(\d+)\]%", $WebUI, $matches)) {
			$ConfigPort = $matches[1];
			foreach ($ct['Ports'] as $port) {
				if ($port['NAT'] && $port['PrivatePort']==$ConfigPort) {$ConfigPort = $port['PublicPort']; break;}
			}
			$WebUI = preg_replace("%\[PORT:\d+\]%", $ConfigPort, $WebUI);
		}
		return $WebUI;
	}

	public function getAllInfo($reload=false) {
		global $dockerManPaths, $host;
		$DockerClient = new DockerClient();
		$DockerUpdate = new DockerUpdate();
		//$DockerUpdate->verbose = $this->verbose;
		$info = DockerUtil::loadJSON($dockerManPaths['webui-info']);
		$autoStart = @file($dockerManPaths['autostart-file'], FILE_IGNORE_NEW_LINES) ?: [];
		foreach ($DockerClient->getDockerContainers() as $ct) {
			$name = $ct['Name'];
			$image = $ct['Image'];
			$tmp = &$info[$name] ?? [];
			$tmp['running'] = $ct['Running'];
			$tmp['autostart'] = in_array($name, $autoStart);
			if (!is_file($tmp['icon']) || $reload) $tmp['icon'] = $this->getIcon($image);
			if ($ct['Running']) {
				$port = &$ct['Ports'][0];
				$ip = ($ct['NetworkMode']=='host'||$port['NAT'] ? $host : $port['IP']);
				$tmp['url'] = strpos($tmp['url'],$ip)!==false ? $tmp['url'] : $this->getControlURL($ct, $ip);
			}
			$tmp['registry'] = $tmp['registry'] ?? $this->getTemplateValue($image, 'Registry');
			$tmp['Support'] = $tmp['Support'] ?? $this->getTemplateValue($image, 'Support');
			$tmp['Project'] = $tmp['Project'] ?? $this->getTemplateValue($image, 'Project');
			if (!$tmp['updated'] || $reload) {
				if ($reload) $DockerUpdate->reloadUpdateStatus($image);
				$vs = $DockerUpdate->getUpdateStatus($image);
				$tmp['updated'] = $vs===null ? null : ($vs===true ? 'true' : 'false');
			}
			if (!$tmp['template'] || $reload) $tmp['template'] = $this->getUserTemplate($name);
			if ($reload) $DockerUpdate->updateUserTemplate($name);
			//$this->debug("\n$name");
			//foreach ($tmp as $c => $d) $this->debug(sprintf('   %-10s: %s', $c, $d));
		}
		DockerUtil::saveJSON($dockerManPaths['webui-info'], $info);
		return $info;
	}

	public function getIcon($Repository) {
		global $docroot, $dockerManPaths;
		$imgUrl = $this->getTemplateValue($Repository, 'Icon');
		preg_match_all("/(.*?):([\w]*$)/i", $Repository, $matches);
		$name = preg_replace("%\/|\\\%", '-', $matches[1][0]);
		$version = $matches[2][0];
		$iconRAM = sprintf('%s/%s-%s-%s.png', $dockerManPaths['images-ram'], $name, $version, 'icon');
		$iconUSB = sprintf('%s/%s-%s-%s.png', $dockerManPaths['images-usb'], $name, $version, 'icon');
		if (!is_dir(dirname($iconRAM))) mkdir(dirname($iconRAM), 0755, true);
		if (!is_dir(dirname($iconUSB))) mkdir(dirname($iconUSB), 0755, true);
		if (!is_file($iconRAM)) {
			if (!is_file($iconUSB)) $this->download_url($imgUrl, $iconUSB);
			@copy($iconUSB, $iconRAM);
		}
		return (is_file($iconRAM)) ? str_replace($docroot, '', $iconRAM) : '';
	}
}

####################################
##       DOCKERUPDATE CLASS       ##
####################################

class DockerUpdate{
	public $verbose = false;

	private function debug($m) {
		if ($this->verbose) echo $m."\n";
	}

	private function xml_encode($string) {
		return htmlspecialchars($string, ENT_XML1, 'UTF-8');
	}

	private function xml_decode($string) {
		return strval(html_entity_decode($string, ENT_XML1, 'UTF-8'));
	}

	public function download_url($url, $path='', $bg=false) {
		exec('curl --max-time 30 --silent --insecure --location --fail '.($path ? ' -o '.escapeshellarg($path) : '').' '.escapeshellarg($url).' '.($bg ? '>/dev/null 2>&1 &' : '2>/dev/null'), $out, $exit_code);
		return ($exit_code===0) ? implode("\n", $out) : false;
	}

	public function download_url_and_headers($url, $headers=[], $path='', $bg=false) {
		$strHeaders = '';
		foreach ($headers as $header) {
			$strHeaders .= ' -H '.escapeshellarg($header);
		}
		exec('curl --max-time 30 --silent --insecure --location --fail -i '.$strHeaders.($path ? ' -o '.escapeshellarg($path) : '').' '.escapeshellarg($url).' '.($bg ? '>/dev/null 2>&1 &' : '2>/dev/null'), $out, $exit_code);
		return ($exit_code===0) ? implode("\n", $out) : false;
	}

	// DEPRECATED: Only used for Docker Index V1 type update checks
	public function getRemoteVersion($image) {
		list($strRepo, $strTag) = explode(':', DockerUtil::ensureImageTag($image));
		$apiUrl = sprintf('http://index.docker.io/v1/repositories/%s/tags/%s', $strRepo, $strTag);
		//$this->debug("API URL: $apiUrl");
		$apiContent = $this->download_url($apiUrl);
		return ($apiContent===false) ? null : substr(json_decode($apiContent, true)[0]['id'], 0, 8);
	}

	public function getRemoteVersionV2($image) {
		list($strRepo, $strTag) = explode(':', DockerUtil::ensureImageTag($image));
		// First - get auth token:
		//   https://auth.docker.io/token?service=registry.docker.io&scope=repository:needo/nzbget:pull
		$strAuthURL = sprintf('https://auth.docker.io/token?service=registry.docker.io&scope=repository:%s:pull', $strRepo);
		//$this->debug("Auth URL: $strAuthURL");
		$arrAuth = json_decode($this->download_url($strAuthURL), true);
		if (empty($arrAuth) || empty($arrAuth['token'])) {
			//$this->debug("Error: Auth Token was missing/empty");
			return null;
		}
		//$this->debug("Auth Token: ".$arrAuth['token']);
		// Next - get manifest:
		//   curl -H 'Authorization: Bearer <TOKEN>' https://registry-1.docker.io/v2/needo/nzbget/manifests/latest
		$strManifestURL = sprintf('https://registry-1.docker.io/v2/%s/manifests/%s', $strRepo, $strTag);
		//$this->debug("Manifest URL: $strManifestURL");
		$strManifest = $this->download_url_and_headers($strManifestURL, ['Accept: application/vnd.docker.distribution.manifest.v2+json', 'Authorization: Bearer '.$arrAuth['token']]);
		if (empty($strManifest)) {
			//$this->debug("Error: Manifest response was empty");
			return null;
		}
		// Look for 'Docker-Content-Digest' header in response:
		//   Docker-Content-Digest: sha256:2070d781fc5f98f12e752b75cf39d03b7a24b9d298718b1bbb73e67f0443062d
		$strDigest = '';
		foreach (preg_split("/\r\n|\r|\n/", $strManifest) as $strLine) {
			if (strpos($strLine, 'Docker-Content-Digest: ') === 0) {
				$strDigest = substr($strLine, 23);
				break;
			}
		}
		if (empty($strDigest)) {
			//$this->debug("Error: Remote Digest was missing/empty");
			return null;
		}
		//$this->debug("Remote Digest: $strDigest");
		return $strDigest;
	}

	// DEPRECATED: Only used for Docker Index V1 type update checks
	public function getLocalVersion($image) {
		$DockerClient = new DockerClient();
		return substr($DockerClient->getImageID($image), 0, 8);
	}

	public function getUpdateStatus($image) {
		global $dockerManPaths;
		$image = DockerUtil::ensureImageTag($image);
		$updateStatus = DockerUtil::loadJSON($dockerManPaths['update-status']);
		if (isset($updateStatus[$image])) {
			if (isset($updateStatus[$image]['status']) && $updateStatus[$image]['status']=='undef') return null;
			if ($updateStatus[$image]['local'] || $updateStatus[$image]['remote']) return ($updateStatus[$image]['local']==$updateStatus[$image]['remote']);
		}
		return null;
	}

	public function reloadUpdateStatus($image=null) {
		global $dockerManPaths;
		$DockerClient = new DockerClient();
		$updateStatus = DockerUtil::loadJSON($dockerManPaths['update-status']);
		$images = ($image) ? [DockerUtil::ensureImageTag($image)] : array_map(function($ar){return $ar['Tags'][0];}, $DockerClient->getDockerImages());
		foreach ($images as $img) {
			$localVersion = null;
			if (!empty($updateStatus[$img]) && array_key_exists('local', $updateStatus[$img])) {
				$localVersion = $updateStatus[$img]['local'];
			}
			$remoteVersion = $this->getRemoteVersionV2($img);
			$status = ($localVersion && $remoteVersion) ? (($remoteVersion == $localVersion) ? 'true' : 'false') : 'undef';
			$updateStatus[$img] = ['local' => $localVersion, 'remote' => $remoteVersion, 'status' => $status];
			//$this->debug("Update status: Image='$img', Local='$localVersion', Remote='$remoteVersion', Status='$status'");
		}
		DockerUtil::saveJSON($dockerManPaths['update-status'], $updateStatus);
	}

	public function setUpdateStatus($image, $version) {
		global $dockerManPaths;
		$image = DockerUtil::ensureImageTag($image);
		$updateStatus = DockerUtil::loadJSON($dockerManPaths['update-status']);
		$updateStatus[$image] = ['local' => $version, 'remote' => $version, 'status' => 'true'];
		//$this->debug("Update status: Image='$image', Local='$version', Remote='$version', Status='true'");
		DockerUtil::saveJSON($dockerManPaths['update-status'], $updateStatus);
	}

	public function updateUserTemplate($Container) {
		$changed = false;
		$DockerTemplates = new DockerTemplates();
		$validElements = ['Support', 'Overview', 'Category', 'Project', 'Icon'];
		$validAttributes = ['Name', 'Default', 'Description', 'Display', 'Required', 'Mask'];
		// Get user template file and abort if fail
		if (!$file = $DockerTemplates->getUserTemplate($Container)) {
			//$this->debug("User template for container '$Container' not found, aborting.");
			return null;
		}
		// Load user template XML, verify if it's valid and abort if doesn't have TemplateURL element
		$template = simplexml_load_file($file);
		if (empty($template->TemplateURL)) {
			//$this->debug("Template doesn't have TemplateURL element, aborting.");
			return null;
		}
		// Load a user template DOM for import remote template new Config
		$dom_local = dom_import_simplexml($template);
		// Try to download the remote template and abort if it fail.
		if (!$dl = $this->download_url($this->xml_decode($template->TemplateURL))) {
			//$this->debug("Download of remote template failed, aborting.");
			return null;
		}
		// Try to load the downloaded template and abort if fail.
		if (!$remote_template = @simplexml_load_string($dl)) {
			//$this->debug("The downloaded template is not a valid XML file, aborting.");
			return null;
		}
		// Loop through remote template elements and compare them to local ones
		foreach ($remote_template->children() as $name => $remote_element) {
			$name = $this->xml_decode($name);
			// Compare through validElements
			if ($name != 'Config' && in_array($name, $validElements)) {
				$local_element = $template->xpath("//$name")[0];
				$rvalue  = $this->xml_decode($remote_element);
				$value   = $this->xml_decode($local_element);
				// Values changed, updating.
				if ($value != $rvalue) {
					$local_element[0] = $this->xml_encode($rvalue);
					//$this->debug("Updating $name from [$value] to [$rvalue]");
					$changed = true;
				}
			// Compare atributes on Config if they are in the validAttributes list
			} elseif ($name == 'Config') {
				$type   = $this->xml_decode($remote_element['Type']);
				$target = $this->xml_decode($remote_element['Target']);
				if ($type == 'Port') {
					$mode = $this->xml_decode($remote_element['Mode']);
					$local_element = $template->xpath("//Config[@Type='$type'][@Target='$target'][@Mode='$mode']")[0];
				} else {
					$local_element = $template->xpath("//Config[@Type='$type'][@Target='$target']")[0];
				}
				// If the local template already have the pertinent Config element, loop through it's attributes and update those on validAttributes
				if (! empty($local_element)) {
					foreach ($remote_element->attributes() as $key => $value) {
						$rvalue  = $this->xml_decode($value);
						$value = $this->xml_decode($local_element[$key]);
						// Values changed, updating.
						if ($value != $rvalue && in_array($key, $validAttributes)) {
							//$this->debug("Updating $type '$target' attribute '$key' from [$value] to [$rvalue]");
							$local_element[$key] = $this->xml_encode($rvalue);
							$changed = true;
						}
					}
				// New Config element, add it to the local template
				} else {
					$dom_remote  = dom_import_simplexml($remote_element);
					$new_element = $dom_local->ownerDocument->importNode($dom_remote, true);
					$dom_local->appendChild($new_element);
					$changed = true;
				}
			}
		}
		if ($changed) {
			// Format output and save to file if there were any commited changes
			//$this->debug("Saving template modifications to '$file");
			$dom = new DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($template->asXML());
			file_put_contents($file, $dom->saveXML());
		} else {
			//$this->debug("Template is up to date.");
		}
	}
}

####################################
##       DOCKERCLIENT CLASS       ##
####################################

class DockerClient {
	private static $containersCache = null;
	private static $imagesCache = null;
	private static $codes = [
		'200' => true, // No error
		'201' => true,
		'204' => true,
		'304' => 'Container already started',
		'400' => 'Bad parameter',
		'404' => 'No such container',
		'409' => 'Image can not be deleted<br><i>In use by other container(s)</i>',
		'500' => 'Server error'
	];

	private function extractID($object) {
		return substr(str_replace('sha256:', '', $object), 0, 12);
	}

	private function usedBy($imageId) {
		$out = [];
		foreach ($this->getDockerContainers() as $ct) {
			if ($ct['ImageId']==$imageId) $out[] = $ct['Name'];
		}
		return $out;
	}

	private function flushCache(&$cache) {
		$cache = null;
	}

	public function flushCaches() {
		$this->flushCache($this::$containersCache);
		$this->flushCache($this::$imagesCache);
	}

	public function humanTiming($time) {
		$time = time() - $time; // to get the time since that moment
		$tokens = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day',3600 => 'hour', 60 => 'minute', 1 => 'second'];
		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits==1)?'':'s').' ago';
		}
	}

	public function formatBytes($size) {
		if ($size == 0) return '0 B';
		$base = log($size) / log(1024);
		$suffix = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		return round(pow(1024, $base - floor($base)), 0).' '.$suffix[floor($base)];
	}

	public function getDockerJSON($url, $method='GET', &$code=null, $callback=null, $unchunk=false) {
		$fp = stream_socket_client('unix:///var/run/docker.sock', $errno, $errstr);
		if ($fp === false) {
			echo "Couldn't create socket: [$errno] $errstr";
			return null;
		}
		$protocol = $unchunk ? 'HTTP/1.0' : 'HTTP/1.1';
		$out = "$method $url $protocol\r\nHost:127.0.0.1\r\nConnection:Close\r\n\r\n";
		fwrite($fp, $out);
		// Strip headers out
		$headers = '';
		while (($line = fgets($fp)) !== false) {
			if (strpos($line, 'HTTP/1') !== false) {$error = vsprintf('%2$s',preg_split("#\s+#", $line)); $code = $this::$codes[$error] ?? "Error code $error";}
			//$headers .= $line;
			if (rtrim($line)=='') break;
		}
		$data = [];
		while (($line = fgets($fp)) !== false) {
			if (is_array($j = json_decode($line, true))) $data = array_merge($data, $j);
			if ($callback) $callback($line);
		}
		fclose($fp);
		return $data;
	}

	public function doesContainerExist($container) {
		foreach ($this->getDockerContainers() as $ct) {
			if ($ct['Name']==$container) return true;
		}
		return false;
	}

	public function doesImageExist($image) {
		foreach ($this->getDockerImages() as $img) {
			if (strpos($img['Tags'][0], $image)!==false) return true;
		}
		return false;
	}

	public function getInfo() {
		$info = $this->getDockerJSON("/info");
		$version = $this->getDockerJSON("/version");
		return array_merge($info, $version);
	}

	public function getContainerLog($id, $callback, $tail=null, $since=null) {
		$this->getDockerJSON("/containers/$id/logs?stderr=1&stdout=1&tail=".urlencode($tail)."&since=".urlencode($since), 'GET', $code, $callback, true);
	}

	public function getContainerDetails($id) {
		return $this->getDockerJSON("/containers/$id/json");
	}

	public function startContainer($id) {
		$this->getDockerJSON("/containers/$id/start", 'POST', $code);
		$this->flushCache($this::$containersCache);
		return $code;
	}

	public function stopContainer($id) {
		$this->getDockerJSON("/containers/$id/stop", 'POST', $code);
		$this->flushCache($this::$containersCache);
		return $code;
	}

	public function restartContainer($id) {
		$this->getDockerJSON("/containers/$id/restart", 'POST', $code);
		$this->flushCache($this::$containersCache);
		return $code;
	}

	public function removeContainer($name, $id, $cache=false) {
		global $docroot, $dockerManPaths;
		$id = $id ?: $name;
		$info = DockerUtil::loadJSON($dockerManPaths['webui-info']);
		// Attempt to remove container
		$this->getDockerJSON("/containers/$id?force=1", 'DELETE', $code);
		if (isset($info[$name])) {
			if (isset($info[$name]['icon'])) {
				$iconRAM = $docroot.$info[$name]['icon'];
				$iconUSB = str_replace($dockerManPaths['images-ram'], $dockerManPaths['images-usb'], $iconRAM);
				if ($cache>=1 && is_file($iconRAM)) unlink($iconRAM);
				if ($cache==2 && $code===true && is_file($iconUSB)) unlink($iconUSB);
			}
			unset($info[$name]);
			DockerUtil::saveJSON($dockerManPaths['webui-info'], $info);
		}
		$this->flushCaches();
		return $code;
	}

	public function pullImage($image, $callback=null) {
		$ret = $this->getDockerJSON("/images/create?fromImage=".urlencode($image), 'POST', $code, $callback);
		$this->flushCache($this::$imagesCache);
		return $ret;
	}

	public function removeImage($id) {
		global $dockerManPaths;
		$image = $this->getImageName($id);
		// Attempt to remove image
		$this->getDockerJSON("/images/$id?force=1", 'DELETE', $code);
		if ($code===true) {
			// Purge cached image information (only if delete was successful)
			$image = DockerUtil::ensureImageTag($image);
			$updateStatus = DockerUtil::loadJSON($dockerManPaths['update-status']);
			if (isset($updateStatus[$image])) {
				unset($updateStatus[$image]);
				DockerUtil::saveJSON($dockerManPaths['update-status'], $updateStatus);
			}
		}
		$this->flushCache($this::$imagesCache);
		return $code;
	}

	public function getDockerContainers() {
		// Return cached values
		if (is_array($this::$containersCache)) return $this::$containersCache;
		$this::$containersCache = [];
		foreach ($this->getDockerJSON("/containers/json?all=1") as $ct) {
			$info = $this->getContainerDetails($ct['Id']);
			$c = [];
			$c['Image']       = DockerUtil::ensureImageTag($ct['Image']);
			$c['ImageId']     = $this->extractID($ct['ImageID']);
			$c['Name']        = substr($info['Name'], 1);
			$c['Status']      = $ct['Status'] ?: 'None';
			$c['Running']     = $info['State']['Running'];
			$c['Cmd']         = $ct['Command'];
			$c['Id']          = $this->extractID($ct['Id']);
			$c['Volumes']     = $info['HostConfig']['Binds'];
			$c['Created']     = $this->humanTiming($ct['Created']);
			$c['NetworkMode'] = $ct['HostConfig']['NetworkMode'];
			$c['BaseImage']   = $ct['Labels']['BASEIMAGE'] ?? false;
			$c['Ports']       = [];
			if (!empty($info['HostConfig']['PortBindings'])) {
				$ports = &$info['HostConfig']['PortBindings'];
				$nat = true;
			} else {
				$ports = &$info['Config']['ExposedPorts'];
				$nat = false;
			}
			$ip = $ct['NetworkSettings']['Networks'][$c['NetworkMode']]['IPAddress'];
			$ports = is_array($ports) ? $ports : array();
			foreach ($ports as $port => $value) {
				list($PrivatePort, $Type) = explode('/', $port);
				$c['Ports'][] = ['IP' => $ip, 'PrivatePort' => $PrivatePort, 'PublicPort' => $nat ? $value[0]['HostPort']:$PrivatePort, 'NAT' => $nat, 'Type' => $Type ];
			}
			$this::$containersCache[] = $c;
		}
		array_multisort(array_column($this::$containersCache,'Name'), SORT_NATURAL|SORT_FLAG_CASE, $this::$containersCache);
		return $this::$containersCache;
	}

	public function getContainerID($Container) {
		foreach ($this->getDockerContainers() as $ct) {
			if (preg_match('%'.preg_quote($Container, '%').'%', $ct['Name'])) return $ct['Id'];
		}
		return null;
	}

	public function getImageID($Image) {
		if (!strpos($Image,':')) $Image .= ':latest';
		foreach ($this->getDockerImages() as $img) {
			foreach ($img['Tags'] as $tag) {
				if ($Image==$tag) return $img['Id'];
			}
		}
		return null;
	}

	public function getImageName($id) {
		foreach ($this->getDockerImages() as $img) {
			if ($img['Id']==$id) return $img['Tags'][0];
		}
		return null;
	}

	public function getDockerImages() {
		// Return cached values
		if (is_array($this::$imagesCache)) return $this::$imagesCache;
		$this::$imagesCache = [];
		foreach ($this->getDockerJSON("/images/json?all=0") as $ct) {
			$c = [];
			$c['Created']     = $this->humanTiming($ct['Created']);
			$c['Id']          = $this->extractID($ct['Id']);
			$c['ParentId']    = $this->extractID($ct['ParentId']);
			$c['Size']        = $this->formatBytes($ct['Size']);
			$c['VirtualSize'] = $this->formatBytes($ct['VirtualSize']);
			$c['Tags']        = array_map('htmlspecialchars', $ct['RepoTags'] ?? []);
			$c['Repository']  = vsprintf('%1$s/%2$s', preg_split("#[:\/]#", DockerUtil::ensureImageTag($ct['RepoTags'][0])));
			$c['usedBy']      = $this->usedBy($c['Id']);
			$this::$imagesCache[$c['Id']] = $c;
		}
		return $this::$imagesCache;
	}
}

##################################
##       DOCKERUTIL CLASS       ##
##################################

class DockerUtil {
	public static function ensureImageTag($image) {
		list($strRepo, $strTag) = array_map('trim', explode(':', $image.':'));
		if (strpos($strRepo, 'sha256:') === 0) {
			// sha256 was provided instead of actual repo name so truncate it for display:
			$strRepo = substr($strRepo, 7, 12);
		} elseif (strpos($strRepo, '/') === false) {
			// Prefix library/ if there's no author (maybe a Docker offical image?)
			$strRepo = "library/$strRepo";
		}
		// Add :latest tag to image if it's absent
		if (empty($strTag)) $strTag = 'latest';
		return "$strRepo:$strTag";
	}

	public static function loadJSON($path) {
		$objContent = (file_exists($path)) ? json_decode(file_get_contents($path), true) : [];
		if (empty($objContent)) $objContent = [];
		return $objContent;
	}

	public static function saveJSON($path, $content) {
		if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
		return file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
	}

	public static function docker($cmd, $a=false) {
		$data = exec("docker $cmd 2>/dev/null", $array);
		return $a ? $array : $data;
	}

	public static function myIP($name, $version=4) {
		$ipaddr = $version==4 ? 'IPAddress' : 'GlobalIPv6Address';
		return static::docker("inspect --format='{{range .NetworkSettings.Networks}}{{.$ipaddr}}{{end}}' $name");
	}
}
?>
