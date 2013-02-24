<?php
require('config.php');
?>
<!DOCTYPE HTML>
<html lang="en-US">
<head>
<meta charset="UTF-8">
<title>Twip 4 - Configuration</title>
<link rel="stylesheet" type="text/css" href="style.css" media="all" />
<meta name="robots" content="noindex,nofollow">
</head>
<body>
	<h1><a href="index.html">Twip<sup title="Version 4">4</sup></a></h1>
	<h2>Twitter API Proxy, redefined.</h2>
	<div>
	
		<h3>API Proxy URL</h3>
		
		<p>
            <input readonly="readonly" type="text" value="<?php echo isset($_GET['api']) ? $_GET['api'] : BASE_URL.'t/'; ?>" onmouseover="this.focus()" onfocus="this.select()" autocomplete="off" />
		</p>

		<p>
			Notice: DO NOT tell your api proxy url to others.
		</p>
		
		<p class="clearfix">
			<a class="button" href="oauth.php">Back</a>
		</p>
		
	</div>		
	<div id="footer">
		2013 &copy; <a href="http://code.google.com/p/twip/">Twip Project</a>
	</div>
</body>
</html>
