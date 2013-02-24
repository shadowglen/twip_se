<?php
session_start();
/*
 * @author tifan
 */
include('include/simple_html_dom.php'); 
/* Credit: */

if(isset($_SESSION['oauth_proxy'])){
    $oAuthEntryPage = $_SESSION['oauth_proxy']['url'];
    $twitterAccount = $_SESSION['oauth_proxy']['username'];
    $twitterPassword = $_SESSION['oauth_proxy']['password'];
}
else{
    header("Location: oauth.php");
    exit();
}

$page_auth = file_get_html($oAuthEntryPage);
if($page_auth === FALSE){
    echo "Cannot load http resource using file_get_contents";
    exit();
}
$oauth_token = $page_auth->find('input[name=oauth_token]', 0)->attr['value'];
$authenticity_token = $page_auth->find('input[name=authenticity_token]', 0)->attr['value'];
$login_fields = Array(
    'oauth_token' => urlencode($oauth_token),
    'authenticity_token' => urlencode($authenticity_token),
    'session[username_or_email]' => urlencode($twitterAccount),
    'session[password]' => urlencode($twitterPassword)
);
foreach($login_fields as $key=>$value) {
  $login_string .= $key.'='.$value.'&';
}
$ckfile = tempnam ("/tmp", "CURLCOOKIE");
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.twitter.com/oauth/authorize');
curl_setopt($ch, CURLOPT_COOKIEJAR, $ckfile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, count($login_fields));
curl_setopt($ch, CURLOPT_POSTFIELDS, $login_string);
$login_result = curl_exec($ch);
curl_close($ch);
$login_obj = str_get_html($login_result);
$login_error = $login_obj->find('div[class=error notice] p', 0)->innertext;
if(strlen($login_error) > 8) {
  /* This is a workaround coz oauth_errors can be "&nbsp;" */
  echo "There must be something wrong with your user account and password combination.<br/>";
  echo "Twitter said: <b>$login_error</b>\n";
  die(-1);
}

  $targetURL = $login_obj->find('div[class=happy notice callback] a', 0)->href;
  header('HTTP/1.1 302 Found');
  header('Status: 302 Found');
  header("Location: $targetURL");
  echo "Please click <a href='$targetURL'>here</a> to continue.";
?>
