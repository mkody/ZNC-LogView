<?php
/*
	ZNC-LogViewer Version 1.2 - 02/08/2015 (ZNC 1.6-compatible)
	A simple script to display ZNC logs online, with basic HTML parsing.

	Copyright (c) 2011 Alex "Antoligy" Wilson <antoligy@antoligy.com>
	Copyright (c) 2015 André "MKody" Fernandes <im@kdy.ch>

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

## -----------------------------------------------------------------------------------------
## Firstly, configuration.

# this would appear to be basic security, you don't need to touch this.
$chdir = array('..', '/', '~', '#', '\\',);

# channels where logs will not be publicly viewable, should be obvious what
#   what this is useful for. (in lowercase)
$denied = array('chambredeluna');

# list of channels
$chan_list = array('lesdossiers','newlunarrepublic','ponyvillelive','salonrp','yayponies');

# the default channel (in lowercase)
$chan = 'newlunarrepublic';

# user used (in lowercase)
$user = 'pfag';

# network used (in lowercase)
$network = 'canternet';

# the path to the log directory itself
$logpath = ('/home/znc/.znc/users/'. $user .'/moddata/log/'. $network .'/');

# the colour scheme
$scheme = array('background' => '#FFFFFF', 'foreground' => '#000000', 'link' => '#0000FF');

# the date and hour format (http://php.net/manual/en/function.date.php)
$dateformat = "d/m/Y";
$hourformat = "H:i:s";

## Config is over, now onto the script itself.
## -----------------------------------------------------------------------------------------

# get human filesize for file
function human_filesize($file, $decimals = 2) {
	$bytes = filesize($file);
	$sz = 'BKMGTP';
	$factor = floor((strlen($bytes) - 1) / 3);
	return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

# is the rewrite set?
if(isset($_GET['rewrite'])) {
	$rewrite = $_GET['rewrite'];
	if($rewrite == "style.css") {
		header("Content-Type: text/css");
		header("X-Content-Type-Options: nosniff"); #IE - http://stackoverflow.com/a/5493459/2900156
		die(file_get_contents("style.css"));
	} elseif($rewrite == "script.js") {
		header("Content-Type: application/javascript");
		header("X-Content-Type-Options: nosniff"); #IE - http://stackoverflow.com/a/5493459/2900156
		die(file_get_contents("script.js"));
	} else {
		# remove trailing slash
		if(substr($rewrite, -1) == "/") $rewrite = substr($rewrite, 0, -1);

		$gets = explode("/", $rewrite);
		$_GET['chan'] = $gets[0];
		$_GET['date'] = $gets[1];
		$_GET['raw'] = $gets[2];
	}
}


# is the channel set?
if(!empty($_GET['chan'])) {
	foreach($denied as $denied) {
		if($_GET['chan'] == $denied) {
			die('Access Denied');
		} else {
			$chan = str_replace($chdir, '', $_GET['chan']);
		}
	}
}

$remove = array('.log', $logpath . '#' . $chan . '/');

# date handling, this pretty much determines whether a log is being displayed or whether channel logs are being listed.
if(!empty($_GET['date'])) {
	$logfile = ($logpath . '#' . $chan . '/' . str_replace($chdir, '', $_GET['date']) . '.log');
	$fh = @fopen($logfile, 'r');
	$logdata = @fread($fh, filesize($logfile));
	@fclose($fh);

	if(!empty($_GET['raw'])) {
		header('Content-type: text/plain');
		die($logdata);
	} else {
		$search_http = "/(http[s]*:\/\/[\S]+)/";
		$replace_http = "<a target=\"_blank\" href=\"\${1}\">\${1}</a>";
		$search_img = '~<a[^>]*?href="(.*?(gif|jpeg|jpg|png))".*?</a>~'; // http://stackoverflow.com/a/2382149
		$replace_img = "<a target=\"_blank\" href=\"\${1}\"><img src=\"\${1}\" /></a>";
		$html_lines = array("\r", "\n");
		$logdata = htmlspecialchars($logdata, ENT_QUOTES | ENT_SUBSTITUTE, 'utf-8');
		if(strpos($logdata,'�') !== false) $bad_encoding = "<br>/!\\ At least someone isn't using UTF-8 encoding!";
		$logdata = preg_replace($search_http, $replace_http, $logdata);
		$logdata = preg_replace($search_img, $replace_img, $logdata);
		$logdata = str_replace($html_lines, "<br />\r\n			", $logdata);
		if(empty($logdata)) $logdata = "<b>Nothing to see here...</b>";
		$title = 'Archive #' . $chan . ' @ ' . date($dateformat, strtotime(substr($logfile, strlen($remove[1]), -4)));
		$nav = "<nav><a href=\"/". $chan ."/\">Go back</a> - <a href=\"#header\">Top</a> / <a href=\"#footer\">Bottom</a> - <a href=\"/" . $chan . "/" . str_replace($remove, "", $logfile) . "/raw/\">RAW</a>". $bad_encoding ."<br>Server time: ". date($dateformat ." ". $hourformat) ."</nav>\r\n";
		$content = "		<p>\r\n			". $logdata ."</p>";
	}
} else {
	$logs = glob($logpath . '#' . $chan . '/*.log');
	krsort($logs);

	$title = 'Archives #' . $chan;
	$nav = "<nav><a href=\"#header\">Top</a> / <a href=\"#footer\">Bottom</a> - <select id=\"chan\"><option value=\"\">Select channel</option>";
	foreach ($chan_list as $chan_name) {
		$nav .= '<option value="/'. $chan_name .'/">#'. $chan_name .'</option>';
	}
	$nav .= "</select></nav>\r\n";
	$content = '		<ul>';
	foreach ($logs as $filename) {
		$content .= "\r\n			<li><a href=\"/" . $chan . "/" . str_replace($remove, "", $filename) . "/\">" . date($dateformat, strtotime(substr($filename, strlen($remove[1]), -4))) . "</a> <small>(". human_filesize($filename) .")</small></li>";
	}
	$content .= "\r\n		</ul>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta charset="utf-8">
	<title><?php echo $title; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="IRC log viewer from <?php echo $user .'@'. $network; ?>">
	<meta name="author" content="<?php echo $user; ?>">
	<link rel="stylesheet" href="/style.css">
	<!--[if lt IE 9]>
	<script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
</head>

<body>
	<header id="header">
		<h1><?php echo $title; ?></h1>
		<?php echo $nav; ?>
	</header>

	<div id="container">
<?php echo $content; ?>

	</div> <!-- /#content -->

	<footer id="footer">
		IRC log viewer from <?php echo $user .'@'. $network; ?> - Page loaded the <?php echo date($dateformat ." \a\\t ". $hourformat); ?> (server time)
	</footer>

	<script src="/script.js"></script>
</body>
</html>
