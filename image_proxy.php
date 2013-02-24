<?php

function imageUpload($oauth_key, $oauth_secret, $token, $type) {
    if(empty($_FILES['media'])) header('HTTP/1.0 400 Bad Request');
    $image = $_FILES['media']['tmp_name'][0];
    $postdata = array
    (
        'status' => empty($_POST['status']) ? '' : sprintf("\0%s",$_POST['status']),
        'media[]' => "@$image", 
    );
    if (!empty($_POST['in_reply_to_status_id']))
        $postdata['in_reply_to_status_id'] = $_POST['in_reply_to_status_id'];
    if (!empty($_POST['lat']))
        $postdata['lat'] = $_POST['lat'];
    if (!empty($_POST['long']))
        $postdata['long'] = $_POST['long'];
    if (!empty($_POST['display_coordinates']))
        $postdata['display_coordinates'] = $_POST['display_coordinates'];

    $url = 'https://api.twitter.com/1.1/statuses/update_with_media.'.$type;
    $consumer = new OAuthConsumer($oauth_key, $oauth_secret);
    $token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);
    $sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
    $request = OAuthRequest::from_consumer_and_token($consumer, $token, 'POST', $url, array());
    $request->sign_request($sha1_method, $consumer, $token);
    // header
    $header = $request->to_header();

    /**** request method ****/ 
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($header)); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

?>
