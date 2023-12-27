<?PHP
error_reporting(E_ERROR);
ini_set('display_errors', '1');
require_once('../libs/vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\ClientException;

class Bind {

  protected $systemConf = null;

  function __construct() {
		
		$mainConf = json_decode(file_get_contents('../conf/main.json'),true);
		$env_conf = json_decode(file_get_contents('../conf/config_'.$mainConf['enviroment'].'.json'),true);			
		$this->systemConf = $env_conf;

  }

  function getToken () {
    global $utils;

    return $utils->connect([
      'url' => $this->systemConf['bind']['server'].'/v1/login/jwt',
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ],
      'username' => $this->systemConf['bind']['user'],
      'password' => $this->systemConf['bind']['pass']
    ]);
  }

  function testEndpoint($table, $fieldList){
    global $utils;
    $tokenData = $this->getToken();
    if ($tokenData['result'] === 0) {
      return $tokenData;
    }
    $token = $tokenData['data']['token'];
    $result =  $utils->connect(array_merge([
      'method' => 'POST',
      'bodyType' => 'json',
      'headers' => [
        'Authorization' => 'JWT '.$token
      ],
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ]
    ], $fieldList));

    return $result;
  }

  function generateCVU($table, $fieldList){
    global $utils;
    $tokenData = $this->getToken();
    if ($tokenData['result'] === 0) {
      return $tokenData;
    }
    $token = $tokenData['data']['token'];
    $result =  $utils->connect([
      'method' => 'POST',
      'bodyType' => 'json',
      'headers' => [
        'Authorization' => 'JWT '.$token
      ],
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ],
      'url' => $this->systemConf['bind']['server'].'/v1/banks/322/accounts/'.$this->systemConf['bind']['account'].'/owner/wallet/cvu',
      'body' => [
        "client_id" => $fieldList['id'],
        "cuit" => $fieldList['cuit'],
        "name" => $fieldList['realname'],
        "currency" => "ARS"
      ]
    ]);

    return $result;
  }

  function getBalance($table, $fieldList) {
    global $utils;
    $tokenData = $this->getToken();
    if ($tokenData['result'] === 0) {
      return $tokenData;
    }
    $token = $tokenData['data']['token'];
    $result =  $utils->connect([
      'method' => 'GET',
      'bodyType' => 'json',
      'headers' => [
        'Authorization' => 'JWT '.$token,
        'obp_from_date' => date('Y-m-d', strtotime('now'))
      ],
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ],
      'url' => $this->systemConf['bind']['server'].'/v1/banks/322/accounts/'.$this->systemConf['bind']['account'].'/owner/transactions'
    ]);

    return $result;
  }

  function setTransaction($table, $fieldList) {
    global $utils;
    $tokenData = $this->getToken();
    if ($tokenData['result'] === 0) {
      return $tokenData;
    }
    $token = $tokenData['data']['token'];
    /*
    [
    'from' => $balance['cvu'],
    'to' => '0000688600000000000017',
    'value' => floatval($fieldList['ars']),
    'description' => 'test'
     ]*/
     if (is_numeric($fieldList['to'])) {
      $to = [
        "cbu" => $fieldList['to']
      ];
     } else {
      $to = [
        "label" => $fieldList['to']
      ];
     }
     $result =  $utils->connect([
      'method' => 'POST',
      'bodyType' => 'json',
      'headers' => [
        'Authorization' => 'JWT '.$token
      ],
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ],
      'url' => $this->systemConf['bind']['server'].'/v1/banks/322/accounts/'.$this->systemConf['bind']['account'].'/owner/transaction-request-types/TRANSFER-CVU/transaction-requests',
      'body' => [
        "origin_id" => date('YmdHis'),
        "origin_debit" => [
            "cvu" => $fieldList['from']
        ],
        "to" => $to,
        "value" => [
            "currency" => "ARS",
            "amount" => $fieldList['value']
        ],
        "description" => $fieldList['description'],
        "concept" => "VAR",
        "emails" => []
      ]
    ]);

    return $result;
      
  }

  function generateAlias($table, $fieldList){
    global $utils;
    $tokenData = $this->getToken();
    if ($tokenData['result'] === 0) {
      return $tokenData;
    }
    $token = $tokenData['data']['token'];
    $result =  $utils->connect([
      'method' => 'POST',
      'bodyType' => 'json',
      'headers' => [
        'Authorization' => 'JWT '.$token
      ],
      'r_options' => [
        'cert' => '/var/dolarizate/'.$this->systemConf['bind']['cert'],
        'ssl_key' => ['/var/dolarizate/client.key', 'mecaenpol']
      ],
      'url' => $this->systemConf['bind']['server'].'/v1/banks/322/accounts/'.$this->systemConf['bind']['account'].'/owner/wallet/alias',
      'body' => [
        "cuit" => $fieldList['cuit'],
        "cvu" => $fieldList['cvu'],
        "label" => $fieldList['alias']
      ]
    ]);

    return $result;
  }

  function webHook($table, $fieldList) {
    file_put_contents('repository/log.txt', json_encode($fieldList)."\n", FILE_APPEND | LOCK_EX);
    http_response_code(200);
    echo '{ "status": "OK" }';
    die();
  }
}