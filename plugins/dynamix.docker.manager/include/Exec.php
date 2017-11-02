<?PHP
/* Copyright 2005-2017, Lime Technology
 * Copyright 2014-2017, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
$docroot = $docroot ?: $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';

if ( isset( $_GET['cmd'] )) {
  $command = rawurldecode(($_GET['cmd']));
  $descriptorspec = [
    0 => ["pipe", "r"],   // stdin is a pipe that the child will read from
    1 => ["pipe", "w"],   // stdout is a pipe that the child will write to
    2 => ["pipe", "w"]    // stderr is a pipe that the child will write to
  ];

  $parts = explode(" ", $command);
  $command = escapeshellcmd(realpath($docroot.array_shift($parts)));
  if (!$command) return;
  $command .= " ".implode(" ", $parts); // should add 'escapeshellarg' here, but this requires changes in all the original arguments
  $id = mt_rand();
  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Command:</legend>";
  echo "root@localhost:# ".addslashes(htmlspecialchars($command))."<br>";
  echo "<span id=\"wait{$id}\">Please wait </span>";
  echo "<p class=\"logLine\" id=\"logBody\"></p></fieldset>');</script>";
  echo "<script>show_Wait({$id});</script>";
  @flush();
  $proc = proc_open($command." 2>&1", $descriptorspec, $pipes, '/', []);
  while ($out = fgets( $pipes[1] )) {
    $out = preg_replace("%[\t\n\x0B\f\r]+%", '', $out );
    @flush();
    echo "<script>addLog(\"" . htmlspecialchars($out) . "\");</script>\n";
    @flush();
  }
  $retval = proc_close($proc);
  echo "<script>stop_Wait($id);</script>\n";
  $out = $retval ?  "The command failed." : "The command finished successfully!";
  echo "<script>addLog('<br><b>".$out. "</b>');</script>";
}
?>
