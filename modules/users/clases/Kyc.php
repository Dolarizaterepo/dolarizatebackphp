<?PHP
error_reporting(E_ERROR);
ini_set('display_errors', '1');

class

Kyc extends DBMaster{

  function generateFormSession($table, $fieldList){
    global $utils;
    $step1 = $utils->connect([
      'url' => 'https://admin.4i4id.com:4443/v1/services/processServices/oauth2/token',
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'verify' => false
      ],
      'client_id' => '76WWdDtn9nbVbb8DT00C1pZBEGOfQBRA',
      'client_secret' => 'Z0TAFjhIZZeCoTVQoSbZKunSXwEaNAij',
      'grant_type' => 'password',
      'provision_key' => 'iwT5kwHNVGTflyaXr0qYvq3MO4y2836R',
      'authenticated_userid' => 'gpxwb'
    ]);
    if ($step1['result'] !== 1) {
      return $step1;
    }
    $token = $step1['data']['access_token'];

    $step2 = $utils->connect([
      'url' => 'https://admin.4i4id.com:4443/v1/scsvc/getAuthorization',
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'verify' => false
      ],
      'headers' => [
        'Authorization' => 'Bearer '.$token
      ],
      'apikey' => 'AR_GPX_WB',
      'seckey' => '64e9315b-f6cb-4eed-aea7-e279d50507ae'
    ]);
    if ($step2['result'] !== 1) {
      return $step2;
    }
    $sessionId = $step2['data']['tokenId'];
    $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJpOFZzQkJQZ05QRlE0RVhnb2EzRHUzd0ROY0ljMTBRNCJ9.TxnY8lPUownuZptFkX7FAtG_bCE58gihLpgXlI0Jtdo';
    $step3 = $utils->connect([
      'url' => 'https://admin.4i4id.com:4443/v1/scsvc/getSessionIDKey?jwt='.$jwt,
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'verify' => false
      ],
      'headers' => [
        'Authorization' => 'Bearer '.$token
      ],
      'apikey' => "AR_GPX_WB",
      'sessionidauth' => $sessionId,
      'responseinf' => "http://164.90.232.127/be/api/users/kyc/captureData"
    ]);
    $step3['data']['trackSession'] = $this->saveData('kycsession', ['user' => $this->me], null);

    return $step3;
  }

  function captureData($table, $fieldList){
    file_put_contents('repository/log_KYC.txt', json_encode($fieldList)."\n", FILE_APPEND | LOCK_EX);
    if (isset($fieldList['decision'])) {
      if ($fieldList['decision'] == 'HIT') {
        $kyclevel = 2;
      } else {
        $kyclevel = 1;
      }
      if (isset($fieldList['externalID'])) {
        $trackSession = $fieldList['externalID'];
      } else if (isset($fieldList['externaltrxid'])) {
        $trackSession = $fieldList['externaltrxid'];
      }
      $userId = $this->find('kycsession', ['id' => $trackSession])['records'][0]['user'];
      $this->saveData('users', [
        'kyclevel' => $kyclevel,
        'kycidtx' => $fieldList['idtx']
      ], $userId, true);
      $this->getData($table, ['user' => $userId, 'idtx' => $fieldList['idtx']]);
    }
    //header("Location: ".$this->systemConf['front']['location'].'close.html');
    header("Location: http://dolarizate.enterprise-its.com/#/unlock");
    die();
  }

  function getData($table, $fieldList) {
    global $utils;
    $step1 = $utils->connect([
      'url' => 'https://admin.4i4id.com:4443/v1/services/processServices/oauth2/token',
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'verify' => false
      ],
      'client_id' => '76WWdDtn9nbVbb8DT00C1pZBEGOfQBRA',
      'client_secret' => 'Z0TAFjhIZZeCoTVQoSbZKunSXwEaNAij',
      'grant_type' => 'password',
      'provision_key' => 'iwT5kwHNVGTflyaXr0qYvq3MO4y2836R',
      'authenticated_userid' => 'gpxwb'
    ]);
    if ($step1['result'] !== 1) {
      return $step1;
    }
    $token = $step1['data']['access_token'];
    $step2 = $utils->connect([
      'url' => 'https://admin.4i4id.com:4443//v1/services/getData',
      'method' => 'POST',
      'bodyType' => 'json',
      'r_options' => [
        'verify' => false
      ],
      'headers' => [
        'Authorization' => 'Bearer '.$token
      ],
      'idtx' => $fieldList['idtx'],
      'apikey' => 'AR_GPX_WB',
      'seckey' => '64e9315b-f6cb-4eed-aea7-e279d50507ae'
    ]);
    if ($step2['result'] !== 1) {
      return $step2;
    }

    if (isset($step2['data']['idmExternal'])) {
      return $this->saveData('users', [
        'dni' => $step2['data']['idmExternal']['dni'],
        'cuit' => $step2['data']['idmExternal']['info3'],
        'realname' => $step2['data']['idmExternal']['nombres'].' '.$step2['data']['idmExternal']['apellidos']
      ], $fieldList['user'], true);
    }
    
    return ['result' => 0];
  }

}