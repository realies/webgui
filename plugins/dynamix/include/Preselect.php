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
// Preselected SMART codes for notifications
$numbers   = [];
$preselect = [['code' =>   5, 'set' => true, 'text' => 'Reallocated sectors count'],
              ['code' => 187, 'set' => true, 'text' => 'Reported uncorrectable errors'],
              ['code' => 188, 'set' => false,'text' => 'Command time-out'],
              ['code' => 197, 'set' => true, 'text' => 'Current pending sector count'],
              ['code' => 198, 'set' => true, 'text' => 'Uncorrectable sector count'],
              ['code' => 199, 'set' => true, 'text' => 'UDMA CRC error rate']];

for ($x = 0; $x < count($preselect); $x++) if ($preselect[$x]['set']) $numbers[] = $preselect[$x]['code'];
$numbers = implode('|',$numbers);
?>