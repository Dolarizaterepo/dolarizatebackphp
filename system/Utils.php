<?PHP

require_once('../libs/vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

class Utils{
    public $system_config;

    function __construct() {
        
        $mainConf = json_decode(file_get_contents('../conf/main.json'),true);
		$env_conf = json_decode(file_get_contents('../conf/config_'.$mainConf['enviroment'].'.json'),true);
        $this->system_config = array_merge($mainConf, $env_conf);
    
    }

    function reverse_norm_entities($d) {
        if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = $this->reverse_norm_entities($v);
            }
        } else if (is_string ($d)) {
			return html_entity_decode($d);
			//return utf8_encode($d);
        }
        return $d;
    }
	
	function norm_entities($d){
		if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = $this->norm_entities($v);
            }
        } else if (is_string ($d)) {
			//return utf8_decode($d);
			return htmlentities($d);
        }
        return $d;
    }

    public function delTree($dir) {
		$files = array_diff(scandir($dir), array('.','..'));
		 
        foreach ($files as $file) {
		   if (is_dir("$dir/$file")) {
              $this->delTree("$dir/$file");
            } else { 
                unlink("$dir/$file");
            }
		 }
		 return rmdir($dir);
	}
    
    public function is_cli(){
        if( defined('STDIN') )
        {
            return true;
        }
        
        if( empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) 
        {
            return true;
        } 
        
        return false;
    }

    public function connect($params) {
        if (
            empty($params) ||
            (!isset($params['url']) && !isset($params['template']) )
        ){
          return [
              'result' => 0,
              'errorcode' => 400,
              'error' => 'Bad request'
          ];
        }
        $restClient = new Client();
        $url = null;
        $warning = [];

        if (isset($params['url'])){
          $url = $params['url'];
          unset($params['url']);
        }

        if ($url == null) {
          $result = [
              'result' => 0,
              'errorcode' => 400,
              'error' => 'Bad request'
          ];  
          if (!empty($warning)){
            $result['warning'] = $warning;
          }
          return $result;
        }

        if (isset($params['method'])){
          $method = $params['method'];
          unset($params['method']);
        } else {
          $method = 'GET';
        }
        $requestParams = [];

        if (isset($params['headers'])){
          $headers = $params['headers'];
          unset($params['headers']);
        } else {
          $headers = [];
        }
        if (!isset($headers['Accept'])){
          $headers['Accept'] = "application/json, text/plain, */*";
        }
        
        $requestParams['headers'] = $headers;

        if (isset($params['basicAuth'])){
          $requestParams['auth'] = $params['basicAuth'];
          unset($params['basicAuth']);
        }

        if (isset($params['r_options'])){
          $requestParams = array_merge($requestParams, $params['r_options']);
        }
        $bodyType = 'raw';

        if (isset($params['bodyType'])){
           $bodyType = $params['bodyType'];
           unset($params['bodyType']);
        }

        if (isset($params['body'])){
          $body = $params['body'];
        } else {
           $body = $params;
        }

        if (!empty($body)){
            if ($bodyType == 'raw') {
            $requestParams['body'] = $body;
            } else if ($bodyType == 'json') {
            $requestParams['json'] = $body;
            } else if ($bodyType == 'form') {
            $requestParams['form_params'] = $body;
            } else {
            return [
                'result' => 0,
                'errorcode' => 400,
                'error' => 'Unknow body type'
            ];   
            }
        }

        try {
            $response = $restClient->request($method, $url, $requestParams);
        } catch (ClientException $e) {
            $responseBody = (string) $e->getResponse()->getBody();
            if (
                $e->getResponse()->hasHeader('Content-Type') && 
                strpos(strtolower($e->getResponse()->getHeader('Content-Type')[0]), 'application/json') !== FALSE
                ){
                $responseBody = json_decode($responseBody, true); 
                }
            
            $result = [
                'result' => 0,
                'errorcode' => $e->getResponse()->getStatusCode(),
                'error' => [
                    'raw' => Psr7\Message::toString($e->getResponse()),
                    'message' => $responseBody
                    ]
                ];
            if (!empty($warning)){
              $result['warning'] = $warning;
            }
            return $result;
            
        }
        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 201 ) {
            $responseBody = (string) $response->getBody();
            if (
                $response->hasHeader('Content-Type') && 
                strpos(strtolower($response->getHeader('Content-Type')[0]), 'application/json') !== FALSE
                ){
                $responseBody = json_decode($responseBody, true); 
                }
                $result = [
                    'result' => 1,
                    'data' => $responseBody,
                    'headers' => $response->getHeaders()
                ];
                if (!empty($warning)){
                    $result['warning'] = $warning;
                }
                return $result; 
        } else {
            
            $result = [
                'result' => 0,
                'errorcode' => $response->getStatusCode(),
                'error' => $response->getReasonPhrase()
            ];
            if (!empty($warning)){
              $result['warning'] = $warning;
            }
            return $result;
        }
    }

    function currentURL () {
      $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
      $currentPage = explode('/', $_SERVER['PHP_SELF']);
      $ruta = $protocol . '://' . $_SERVER['HTTP_HOST']. implode('/', array_slice($currentPage, 0, -1));
      return $ruta;
    }
}