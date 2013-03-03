<?php
session_start();
require('include/twitteroauth.php');
require('config.php');
if (isset($_POST['url_suffix'])) {
    $_SESSION['url_suffix'] = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['url_suffix']);
}
if (!empty($_POST)) {
    $_SESSION['oauth_proxy'] = array(
        'username' => $_POST['username'],
        'password' => $_POST['password'],
    );
    $_SESSION['userapikey'] = $_POST['userapikey'];
    $_SESSION['userapisecret'] = $_POST['userapisecret'];
    if ($_SESSION['userapikey'] == '' || $_SESSION['userapisecret'] == '') {
        $_SESSION['userapikey'] = OAUTH_KEY;
        $_SESSION['userapisecret'] = OAUTH_SECRET;
    }

    $connection = new TwitterOAuth($_SESSION['userapikey'], $_SESSION['userapisecret']);
    $request_token = $connection->getRequestToken(BASE_URL . 'oauth.php');

    /* Save request token to session */
    $_SESSION['oauth_token'] = $request_token['oauth_token'];
    $_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

    switch ($connection->http_code) {
        case 200:
            /* Build authorize URL */
            $url = $connection->getAuthorizeURL($_SESSION['oauth_token'], FALSE);
            $_SESSION['oauth_proxy']['url'] = $url;
            // encode user and password for decode.
            header('HTTP/1.1 302 Found');
            header('Status: 302 Found');
            header('Location: oauth_proxy.php');
            break;
        default:
            echo 'Could not connect to Twitter. Refresh the page or try again later.';
            echo "\n Error code:" . $connection->http_code . ".";
            if ($connection->http_code == 0) {
                echo "Don't report bugs or issues if you got this error code. Twitter is not accessible on this host. Perhaps the hosting company blocked Twitter.";
            }
            break;
    }
    exit();
}
if (isset($_GET['oauth_token']) && isset($_GET['oauth_verifier'])) {
    $connection = new TwitterOAuth($_SESSION['userapikey'], $_SESSION['userapisecret'], $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
    $access_token = $connection->getAccessToken($_GET['oauth_verifier']);
    $access_token['userapikey'] = $_SESSION['userapikey'];
    $access_token['userapisecret'] = $_SESSION['userapisecret'];
    if ($connection->http_code == 200) {
        if (INVITEMODE) {
		$allowed_uids = file("allowed_uids");
        	for ($i = 0; $i < count($allowed_uids); $i++) {
            		$allowed_uids[$i] = rtrim($allowed_uids[$i]);
        	}
        	if (!in_array($access_token['user_id'], $allowed_uids)) {
            		header('HTTP/1.1 401 Unauthorized');
            		header('WWW-Authenticate: Basic realm="Twip4 Override Mode"');
            		echo 'This user is not allowed to use the api proxy.' . "\n";
            		exit();
        	}
	}

        $old_tokens = glob('oauth/*.' . $access_token['user_id']);
        if (!empty($old_tokens)) {
            foreach ($old_tokens as $file) {
                unlink($file);
            }
        }
        if ($_SESSION['url_suffix'] == '') {
            for ($i = 0; $i < 6; $i++) {
                $d = rand(1, 30) % 2;
                $suffix_string .= $d ? chr(rand(65, 90)) : chr(rand(48, 57));
            }
        } else {
            $suffix_string = $_SESSION['url_suffix'];
        }
        if (file_put_contents('oauth/' . $suffix_string . '.' . $access_token['user_id'], serialize($access_token)) === FALSE) {
            echo 'Error failed to write access_token file.Please check if you have write permission to oauth/ directory' . "\n";
            exit();
        }
        $url = BASE_URL . 'o/' . $suffix_string;
        header('HTTP/1.1 302 Found');
        header('Status: 302 Found');
        header('Location: getapi.php?api=' . $url);
    } else {
        echo 'Error ' . $connection->http_code . "\n";
        print_r($connection);
    }
    exit();
}
?>
<!DOCTYPE HTML>
<html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>Twip 4 - Configuration</title>
        <link rel="stylesheet" type="text/css" href="style.css" media="all" />
        <meta name="robots" content="noindex,nofollow">
    </head>
    <body>
        <script type="text/javascript">
            function checkValid() {
                username=document.mainform.username.value;
                password=document.mainform.password.value;
                userapikey=document.mainform.userapikey.value;
                userapisecret=document.mainform.userapisecret.value;
                if (username.length==0 || password.length==0) {
                    alert("Both username and password are needed.");
                    return false;
                }
                if (userapikey.length==0 ^ userapisecret.length==0) {
                    alert("Please completely fill your api consumer token or leave them blank.");
                    return false;
                }
                return true;
            }
        </script>
        <h1>Twip<sup title="Version 4">4</sup></h1>
        <h2>Twitter API Proxy, redefined.</h2>
        <div>

            <form action="" method="post" onSubmit="return checkValid()" name="mainform">

                <p>
                    <label for="url_suffix">1. Preferred URL<span class="small"></span></label>
                    <input class="half" type="text" value="<?php echo BASE_URL . 'o/'; ?>" id="base_url" disabled autocomplete="off" />
                    <input class="half" type="text" value="" id="url_suffix" name="url_suffix" autocomplete="off" />
                </p>

                <p>
                    <label for="username">2. Twitter Username<span class="small">REQUIRED*</span></label>
                    <input type="text" value="" id="username" name="username" autocomplete="off" />
                </p>

                <p>
                    <label for="password">3. Twitter Password<span class="small">REQUIRED*</span></label>
                    <input type="password" value="" id="password" name="password" autocomplete="off" />
                </p>

                <p>
                    <label for="userapikey">4. API Key<span class="small">Left it empty or use your API Key</span></label>
                    <input type="text" value="" id="userapikey" name="userapikey" autocomplete="off" />
                </p>

                <p>
                    <label for="userapisecret">5. API Secret<span class="small">Left it empty or use your API Secret</span></label>
                    <input type="text" value="" id="userapisecret" name="userapisecret" autocomplete="off" />
                </p>
                <p>If you use your own API Key & Secret, lease set the callback url of your twitter app to <?php echo BASE_URL; ?></p>

                <div id="submit"><input type="submit" value="Authorization" /></div>

            </form>

        </div>		
        <div id="footer">
            2013 &copy; <a href="http://code.google.com/p/twip/">Twip Project</a>
        </div>
    </body>
</html>
