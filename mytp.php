<?php
require('include/twitteroauth.php');
require('image_proxy.php');
class twip{
    const PARENT_API = 'https://api.twitter.com/';
    const ERR_LOGFILE = 'err.txt';
    const LOGFILE = 'log.txt';
    const LOGTIMEZONE = 'Etc/GMT-8';
    const BASE_URL = 'http://yegle.net/twip/';
    const API_VERSION = '1.1';
    const EXPAND_URL = '1.1';

    public function replace_tco_json(&$status){
        if(!isset($status->entities)){
            return;
        }
        mb_internal_encoding('UTF-8');

	if(isset($status->entities->urls)){
        	$a = array_reverse($status->entities->urls);
        	foreach($a as &$url){
            		if($url->expanded_url){
                		$status->text = mb_substr($status->text, 0, $url->indices[0]) . $url->expanded_url . mb_substr($status->text, $url->indices[1]);
                		$url->indices[1] = $url->indices[0] + mb_strlen($url->expanded_url);
                		$url->url = $url->expanded_url;
            		}
        	}
        	$status->entities->urls = array_reverse($a);
	}

        if(!isset($status->entities->media)){
            return;
        }
        $a = array_reverse($status->entities->media);
        foreach($status->entities->media as &$media){
            $status->text = mb_substr($status->text, 0, $media->indices[0]) . $media->media_url_https . mb_substr($status->text, $media->indices[1]);
            $media->indices[1] = $media->indices[0] + mb_strlen($media->media_url_https);
            $media->url = $media->media_url_https;
        }
        $status->entities->media = array_reverse($a);
    }

    public function json_x86_decode($in){
        $in = preg_replace('/id":(\d+)/', 'id":"\1"', $in);
        return json_decode($in);
    }
    public function json_x86_encode($in){
        $in = json_encode($in);
        return preg_replace('/id":"(\d+)"/', 'id":\1', $in);
    }

    public function parse_entities($status, $type){
        if($type == 'json'){
            $j = is_string($status) ? $this->json_x86_decode($status) : $status;
            if(is_array($j)){
                foreach($j as &$s){
                    $s = $this->parse_entities($s, $type);
                }
            }
            elseif($this->expand_url){
                $this->replace_tco_json($j);
                if(isset($j->status)){
                    $this->replace_tco_json($j->status);
                }
                if(isset($j->retweeted_status)){
                    $this->replace_tco_json($j->retweeted_status);
                }
                if(isset($j->status->retweeted_status)){
                    $this->replace_tco_json($j->status->retweeted_status);
                }
            }
            return is_string($status) ? $this->json_x86_encode($j) : $j;
        }
        return $status;
    }

    public function twip($options = null){
        $this->parse_variables($options);

        ob_start();
        $compressed = $this->compress && Extension_Loaded('zlib') && ob_start("ob_gzhandler");

        if($this->mode=='o'){
            $this->override_mode();
        }
        else{
            header('HTTP/1.0 400 Bad Request');
            exit();
        }

        $str = ob_get_contents();
        if ($compressed) ob_end_flush();
        header('Content-Length: '.ob_get_length());
        ob_flush();

        if($this->debug){
            print_r($this);
            print_r($_SERVER);
            file_put_contents('debug',ob_get_contents().$str);
            ob_clean();
        }
        if($this->dolog){
            file_put_contents('log',$this->method.' '.$this->request_uri."\n",FILE_APPEND);
        }
    }

    private function echo_token(){
            $str = 'oauth_token='.$this->access_token['oauth_token']."&oauth_token_secret=".$this->access_token['oauth_token_secret']."&user_id=".$this->access_token['user_id']."&screen_name=".$this->access_token['screen_name'].'&x_auth_expires=0'."\n";
            echo $str;
    }

    private function parse_variables($options){
        //parse options
        $this->parent_api = isset($options['parent_api']) ? $options['parent_api'] : self::PARENT_API;
        $this->api_version = isset($options['api_version']) ? $options['api_version'] : self::API_VERSION;
        $this->debug = isset($options['debug']) ? !!$options['debug'] : FALSE;
        $this->dolog = isset($options['dolog']) ? !!$options['dolog'] : FALSE;
        $this->compress = isset($options['compress']) ? !!$options['compress'] : FALSE;
        $this->oauth_key = $options['oauth_key'];
        $this->oauth_secret = $options['oauth_secret'];
	$this->expand_url = isset($options['expand_url']) ? !!$options['expand_url'] : FALSE;

        if(substr($this->parent_api, -1) !== '/') $this->parent_api .= '/';

        $this->base_url = isset($options['base_url']) ? trim($options['base_url'],'/').'/' : self::BASE_URL;
        if(preg_match('/^https?:\/\//i',$this->base_url) == 0){
            $this->base_url = 'http://'.$this->base_url;
        }

        //parse $_SERVER
        $this->method = $_SERVER['REQUEST_METHOD'];


        $this->parse_request_uri();
    }

    private function override_mode(){
        $tokenfile = glob('oauth/'.$this->password.'.*');
        if(!empty($tokenfile)){
            $access_token = @file_get_contents($tokenfile[0]);
        }
        if(empty($access_token)){
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Basic realm="Twip4 Override Mode"');
            echo 'You are not allowed to use this API proxy';
            exit();
        }
        $access_token = unserialize($access_token);
        $this->access_token = $access_token;
	$this->oauth_key = $access_token['userapikey'];
	$this->oauth_secret = $access_token['userapisecret'];

        if(preg_match('!oauth/access_token\??!', $this->request_uri)){
            $this->echo_token();
            return;
        }

        if(preg_match('/^[^?]+\.json/', $this->request_uri)){
            $type = 'json';
        } else {
            $type = 'xml';
        }

        if($this->request_uri == null){
            echo 'click <a href="'.$this->base_url.'oauth.php">HERE</a> to get your API url';
            return;
        }
        $this->parameters = $this->get_parameters();
        $this->uri_fixer();
        $this->connection = new TwitterOAuth($this->oauth_key, $this->oauth_secret, $this->access_token['oauth_token'], $this->access_token['oauth_token_secret']);

        if(!isset($_REQUEST['include_entities'])){
            if(preg_match('/^[^?]+\?/', $this->request_uri)){
                $this->request_uri .= '&include_entities=true';
            }
            else{
                $this->request_uri .= '?include_entities=true';
            }
        }

        if(strpos($this->request_uri,'statuses/update_with_media') > 0){
	    echo $this->parse_entities(imageUpload($this->oauth_key, $this->oauth_secret, $this->access_token, $type), $type);
	    return;
        }

        switch($this->method){
            case 'POST':
                echo $this->parse_entities($this->connection->post($this->request_uri,$this->parameters), $type);
                break;
            case 'DELETE':
                echo $this->parse_entities($this->connection->delete($this->request_uri,$this->parameters), $type);
                break;
            default:
                echo $this->parse_entities($this->connection->get($this->request_uri), $type);
                break;
        }
    }

    private function uri_fixer(){
        // $api is the API request without version number
        list($version, $api) = $this->extract_uri_version($this->request_uri);

        // If user specified version, use that version. Else use default version
        $version = ($version == "") ? $this->api_version : $version;

        $replacement = array(
            'pc=true' => 'pc=false', //change pc=true to pc=false
            '&earned=true' => '', //remove "&earned=true"
        );

        $api = str_replace(array_keys($replacement), array_values($replacement), $api);

        if( strpos($api,'oauth/') === 0
            || strpos($api, 'i/') === 0 ){
            // These API requests don't needs version string
            $this->request_uri = sprintf("%s%s", $this->parent_api, $api);
        }else{
            $this->request_uri = sprintf("%s%s/%s", $this->parent_api, $version, $api);
        }
    }

    public function extract_uri_version($uri){
        $re = '/^(([0-9.]+)\/)?(.*)/';

        preg_match($re, $uri, $matches);

        $version = $matches[2];
        $api = $matches[3];
        return array($version, $api);
    }

    private function parse_request_uri(){
        $full_request_uri = substr($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],strlen(preg_replace('/^https?:\/\//i','',$this->base_url)));
        if(strpos($full_request_uri,'o/')===0){
            list($this->mode,$this->password,$this->request_uri) = explode('/',$full_request_uri,3);
            $this->mode = 'o';
        }
        $this->request_uri = preg_replace('/\/+/','/',$this->request_uri);
    }

    private function headerfunction($ch,$str){
        if(strpos($str,'Content-Length:')!==NULL){
            header($str);
        }
        $this->response_headers[] = $str;
        return strlen($str);
    }

    private function get_parameters($returnArray = TRUE){
        $data = file_get_contents('php://input');
        if(!$returnArray) return $data;
        $ret = array();
        parse_str($data,$ret);
        return $ret;
    }
}
?>
