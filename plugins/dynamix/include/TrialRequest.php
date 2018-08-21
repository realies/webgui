<?PHP
/* Copyright 2005-2018, Lime Technology
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
require_once "$docroot/webGui/include/Helpers.php";

$var = parse_ini_file('state/var.ini');
?>
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-fonts.css")?>">
<link type="text/css" rel="stylesheet" href="<?autov("/webGui/styles/default-popup.css")?>">
<script src="/webGui/javascript/dynamix.js"></script>
<script>
function registerTrial(email, guid) {
  if (email.length) {
    var timestamp = <?=time()?>;
    $('#status_panel').slideUp('fast');
    $('#trial_form').find('input').prop('disabled', true);
    // Nerds love spinners, Maybe place a spinner image next to the submit button; we'll show it now:
    $('#spinner_image').fadeIn('fast');

    $.post('https://keys.lime-technology.com/account/trial',{timestamp:timestamp,guid:guid,email:email},function(data) {
        $('#spinner_image').fadeOut('fast');
        var msg = "<p>Thank you for registering USB Flash GUID <strong>"+guid+"</strong></p>" +
                  "<p>An email has been sent to <strong>"+email+"</strong> containing your key file URL." +
                  " When received, please paste the URL into the <i>Key file URL</i> box and" +
                  " click <i>Install Key</i>.</p>" +
	          "<p>If you do not receive an email, please check your spam or junk-email folder.</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
        $('#trial_form').fadeOut('fast');
    }).fail(function(data) {
        $('#trial_form').find('input').prop('disabled', false);
        $('#spinner_image').fadeOut('fast');
        var status = data.status;
        var obj = data.responseJSON;
        var msg = "<p>Sorry, an error ("+status+") occurred registering USB Flash GUID <strong>"+guid+"</strong><p>" +
                  "<p>The error is: "+obj.error+"</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
    });
  }
}
</script>
<body>
<div style="margin-top:20px;line-height:30px;margin-left:40px">
<div id="status_panel"></div>
<form markdown="1" id="trial_form">

Email address: <input type="text" name="email" maxlength="1024" value="" style="width:33%">

<input type="button" value="Register Trial" onclick="registerTrial(this.form.email.value.trim(), '<?=$var['flashGUID']?>')">

<p>A link to your <i>Trial</i> key will be delivered to this email address.

<p><strong>Note:</strong>
Per our <a target="_blank" href="https://lime-technology.com/policies/">Policy Statement</a>, we never send
unsolicited email to anyone, nor do we authorize anyone else to do so on our behalf.

<p><a target="_blank" href="/Tools/EULA">End-User License Agreement</a>.

</form>
</div>
</body>
