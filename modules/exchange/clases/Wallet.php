<?PHP

class Wallet extends DBMaster{

  function exchange($table, $fieldList){
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    $balance = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$this->me)['records'][0];
    if ($fieldList['dir'] == 1) {
      // From ARS to USD
      if (floatval($fieldList['ars']) > floatval($balance['ars'])) {
        return [
          'result' => 0,
          'message' => 'Fondos insuficientes'
        ];
      }
      $ars = floatval($balance['ars']) - floatval($fieldList['ars']);
      $usd = floatval($balance['usd']) + floatval($fieldList['usd']); 
      $this->execQuery('UPDATE `wallet` SET `ars` = '.$ars.', `usd` = '.$usd.' WHERE `user`='.$this->me);
      $ref = $bind->setTransaction($table, [
        'from' => $balance['cvu'],
        'to' => $this->systemConf['bind']['mainCVU'],
        'value' => floatval($fieldList['ars']),
        'description' => 'UserToMain'
      ]);
      $this->updateBalance(2, 'Compra USD', $fieldList['usd'], floatval($fieldList['ars']) * -1, json_encode($ref['data']['transaction_ids']));
      
    } else {
      // From USD to ARS
      if (floatval($fieldList['usd']) > floatval($balance['usd'])) {
        return [
          'result' => 0,
          'message' => 'Fondos insuficientes'
        ];
      }
      $ars = floatval($balance['ars']) + floatval($fieldList['ars']);
      $usd = floatval($balance['usd']) - floatval($fieldList['usd']); 
      $this->execQuery('UPDATE `wallet` SET `ars` = '.$ars.', `usd` = '.$usd.' WHERE `user`='.$this->me);
      $ref = $bind->setTransaction($table, [
        'to' => $balance['cvu'],
        'from' => $this->systemConf['bind']['mainCVU'],
        'value' => floatval($fieldList['ars']),
        'description' => 'MainToUser'
      ]);
      $this->updateBalance(2, 'Venta USD', floatval($fieldList['usd']) * -1, floatval($fieldList['ars']), json_encode($ref['data']['transaction_ids']));
    }
    return ['result' => 1];
  }

  function simIn ($table, $fieldList){ 
    $this->execQuery('UPDATE `wallet` SET `ars` = `ars` + '.$fieldList['value'].' WHERE `user`='.$this->me);
    $this->updateBalance('0', 'Deposito ARS', '0', floatval($fieldList['value']));
    return ['result' => 1];
  }

  function simOut ($table, $fieldList){ 
    $balance = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$this->me)['records'][0];
    $value = floatval($fieldList['value']);
    $ars = floatval($balance['ars']);
    $usd = floatval($balance['usd']);
    if ($ars < $value) {
      $value -= $ars;
      $ars = 0;
      if ($usd * 300 < $value) {
        return [
          'result' => 0,
          'message' => 'Fondos insuficientes'
        ];
      } else {
        $usd -= $value / 300;
      }
    } else {
      $ars -= $value;
    }
    $this->execQuery('UPDATE `wallet` SET `ars` = '.$ars.', `usd` = '.$usd.' WHERE `user`='.$this->me);
    $this->updateBalance(1, 'Extraccion de efectivo', $usd - floatval($balance['usd']) , $ars - floatval($balance['ars']));
    return ['result' => 1];
  }

  function transfer ($table, $fieldList){ 
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    if ($fieldList['destinationtype'] == 'user') {
      $destinationData = $this->execQuery('SELECT * FROM `users` WHERE `tag` LIKE "'.$fieldList['destination'].'"')['records'];
      if (count($destinationData) > 0 ) {
        $destinationUser = $destinationData[0]['id'];
        if ($destinationUser == $this->me) {
          return [
            'result' => 0,
            'message' => 'Invalid tag'
          ];
        } else {
          $destinationWallet = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$destinationUser)['records'];
          if ($destinationWallet[0]['cvu'] == null) {
            return [
              'result' => 0,
              'message' => 'Inactive account'
            ];
          } else {
            $destination = $destinationWallet[0]['cvu'];
            $local = true;
          }
        }
      } else {
        return [
          'result' => 0,
          'message' => 'Invalid tag'
        ];
      }
    } else if ($fieldList['destinationtype'] == 'account') {
      $destination = $fieldList['destination'];
      $local = false;
      $destinationWallet = $this->execQuery('SELECT * FROM `wallet` WHERE `cvu`="'.$destination.'"')['records'];
      if (count($destinationWallet) > 0) {
        $local = true;
      }
    }
    
    
    $balance = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$this->me)['records'][0];
    $value = floatval($fieldList['value']);
    $ars = floatval($balance['ars']);
    $usd = floatval($balance['usd']);
    $sendArs = 0;
    $sendUsd = 0;
    if ($fieldList['currency'] == 'ars') {
      if ($ars < $value) {
        return [
          'result' => 0,
          'message' => 'Fondos insuficientes'
        ];
      } else {
        $ars -= $value;
        $sendArs = $value;
      }
    } else if ($fieldList['currency'] == 'usd') {
      if ($usd < $value) {
        return [
          'result' => 0,
          'message' => 'Fondos insuficientes'
        ];
      } else {
        $usd -= $value;
        $sendUsd = $value;
      }
    }
    $this->execQuery('UPDATE `wallet` SET `ars` = '.$ars.', `usd` = '.$usd.' WHERE `user`='.$this->me);
    
    if ($fieldList['currency'] == 'ars') {
      $ref = $bind->setTransaction($table, [
        'from' => $balance['cvu'],
        'to' => $destination,
        'value' => floatval($value),
        'description' => 'UserToUser'
      ]);
      $this->updateBalance(1, 'Extraccion de efectivo', $usd - floatval($balance['usd']) , $ars - floatval($balance['ars']), json_encode($ref['data']['transaction_ids']));
      if ($local) {
        $this->execQuery('UPDATE `wallet` SET `usd` = '.$sendUsd + floatval($destinationWallet[0]['usd']).', `ars` = '.$sendArs + floatval($destinationWallet[0]['ars']).' WHERE `user`='.$destinationWallet[0]['user']);
        $this->updateBalance(1, 'Deposito de efectivo', $sendUsd , $sendArs, json_encode($ref['data']['transaction_ids']), $destinationWallet[0]['user']);
      }
    } else if ($fieldList['currency'] == 'usd') {
      $this->execQuery('UPDATE `wallet` SET `usd` = '.$usd + floatval($destinationWallet[0]['usd']).' WHERE `user`='.$destinationUser);
      $this->updateBalance(1, 'Extraccion de efectivo', $usd - floatval($balance['usd']) , $ars - floatval($balance['ars']));
      $this->updateBalance(1, 'Deposito de efectivo', $value , 0, null, $destinationUser);
    }
    return ['result' => 1];
  }

  function getCotizacion($table, $fieldList){ 
    global $utils;
    //return $utils->connect(['url' => 'https://www.dolarsi.com/api/api.php?type=valoresprincipales']);
    $nonce = time();
    $method = 'POST';
    $request = '/api/v3/currency_conversions/';
    $payload = [
      'from_currency' => 'ars',
      'to_currency' => 'usd',
      'spend_amount' => 200
    ];
    $secret = '92c5b136eff884cf0b506682d912f655';
    $signature = hash_hmac('sha256', $nonce.$method.$request.json_encode($payload), $secret);
    $header = 'Bitso '.'JTpyeEiEJX'.':'.$nonce.':'.$signature;
    $compra = $utils->connect(
      [
        'url' => 'https://api.bitso.com'.$request,
        'method' => $method,
        'bodyType' => 'json',
        'headers' => [
          'Authorization' => $header
        ],
        'body' => $payload
      ]
    );
    $nonce = time() + 1;
    $method = 'POST';
    $request = '/api/v3/currency_conversions/';
    $payload = [
      'from_currency' => 'usd',
      'to_currency' => 'ars',
      'spend_amount' => 200
    ];
    $secret = '92c5b136eff884cf0b506682d912f655';
    $signature = hash_hmac('sha256', $nonce.$method.$request.json_encode($payload), $secret);
    $header = 'Bitso '.'JTpyeEiEJX'.':'.$nonce.':'.$signature;
    $venta = $utils->connect(
      [
        'url' => 'https://api.bitso.com'.$request,
        'method' => $method,
        'bodyType' => 'json',
        'headers' => [
          'Authorization' => $header
        ],
        'body' => $payload
      ]
    );
    return [
      'result' => 1,
      'compra' => $compra['data']['payload']['rate'],
      'venta' => $venta['data']['payload']['rate']
    ];
  }

  function updateBalance($type, $description, $usd, $ars, $txid = null, $user = null) {
    if ($user == null) {
      $balance = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$this->me)['records'][0];
    } else {
      $balance = $this->execQuery('SELECT * FROM `wallet` WHERE `user`='.$user)['records'][0];
    }
    $this->saveData('balance', [
      'wallet' => $balance['id'],
      'type' => $type,
      'description' => $description,
      'usd' => sprintf("%f",$usd),
      'ars' => sprintf("%f",$ars),
      'bars' => $balance['ars'],
      'busd' => $balance['usd'],
      'txid' => $txid
    ], null);
  }

  function getBalance($table, $fieldList){ 
    $paginate = $this->paginate($fieldList);
    $result = $this->execQuery('SELECT `b`.* FROM `balance` as `b` LEFT JOIN `wallet` as `w` ON `w`.`id` = `b`.`wallet` where `w`.`user` ='.$this->me.' ORDER BY `b`.`created` DESC'.$paginate);
    return $result;
  }

  function createCVU($table, $fieldList) {
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    $userInfo = $this->execQuery('SELECT * FROM `users` WHERE `id` = '.$this->me)['records'][0];
    $result = $bind->generateCVU($table, $userInfo);
    if ($result['result'] == 0 || isset($result['data']['error'])) {
      return $result;
    }
    return $this->execQuery('UPDATE `wallet` SET `cvu`="'.$result['data']['cvu'].'" WHERE `user` ='.$this->me);
  }

  function conciliate($table, $fieldList) {
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    try {
      $raw = $bind->getBalance($table, $fieldList);
    } catch (Exception $e) {
      $shellReturn = exec('/usr/sbin/ipsec restart');
      return ['result' => 0, 'error' => $e->getMessage(), 'shell' => $shellReturn];
    }
    $accounts = [];
    $wallets = $this->find($table, $fieldList);
    foreach($wallets['records'] as $account) {
      if ($account['cvu'] != '' || $account['cvu'] != null) {
        $accounts[$account['cvu']]= $account;
      }
    }
    $transactions = [];
    foreach($raw['data'] as $transaction) {
      if (in_array($transaction['this_account']['account_routing']['address'], array_keys($accounts))) {
        $transactions []= $transaction;
        $inBalance = $this->execQuery('SELECT * FROM `balance` WHERE txid like \'%"'.$transaction['details']['reference_number'].'"%\';')['records'];
        if (count($inBalance) === 0) {
          $walletData = $accounts[$transaction['this_account']['account_routing']['address']];
          $amount = floatval($transaction['details']['value']['amount']);
          $amount -= $amount * 0.006;
          $this->execQuery('UPDATE `wallet` SET `ars` = `ars` + '.$amount.' WHERE `id` = "'.$walletData['id'].'"');
          $this->saveData('balance', [
            'wallet' => $walletData['id'],
            'type' => '0',
            'description' => $transaction['details']['description'],
            'usd' => '0',
            'ars' => sprintf("%f",$amount),
            'bars' => floatval($walletData['ars']) + $amount,
            'busd' => $walletData['usd'],
            'txid' => '"'.$transaction['details']['reference_number'].'"'
          ], null, true);
        }
      }
    }
    return ['result' => 1, 'data' => $transactions, 'raw' => $raw['data'], 'accounts' => $accounts];
  }

  function getReport($table, $fieldList) {
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    try {
      $raw = $bind->testEndpoint($table, ['url' => 'https://api.bind.com.ar/v1/banks/322/accounts/owner', 'method' => 'GET']);
    } catch (Exception $e) {
      $shellReturn = exec('/usr/sbin/ipsec restart');
      return ['result' => 0, 'error' => $e->getMessage(), 'shell' => $shellReturn];
    }
    $wallets = $this->execQuery("SELECT SUM(`ars`) as `ars`, SUM(`usd`) as `usd` FROM `wallet`")['records'][0];
    return ['result' => 1, 'bind' => $raw['data'], 'wallets' => $wallets, 'mainAccount' => $this->systemConf['bind']['account']];
  }

  function setAlias($table, $fieldList) {
    $userInfo = $this->execQuery('SELECT `w`.*, `u`.`cuit` FROM `wallet` as `w` LEFT JOIN `users` as `u` ON `u`.`id` = `w`.`user` WHERE `u`.`id` = '.$this->me)['records'][0];
    if ($userInfo['cvu'] == null) {
      return ['result' => 0, 'error' => 'No CVU found'];
    }
    include_once('../modules/exchange/clases/Bind.php');
    $bind = new Bind();
    try {
      $raw = $bind->generateAlias($table, [
        'cuit' => $userInfo['cuit'],
        'cvu' => $userInfo['cvu'],
        'alias' => $fieldList['alias']
      ]);
    } catch (Exception $e) {
      return ['result' => 0, 'error' => $e->getMessage()];
    }
    if ($raw['data']['status'] == 'OK') {
      $wallets = $this->execQuery("UPDATE `wallet` SET `alias` = '".$fieldList['alias']."' WHERE `wallet`.`user` = ".$userInfo['user'])['records'][0];
      return ['result' => 1, 'bind' => $raw['data']];
    } else {
      return ['result' => 1, 'bind' => $raw['error']['message']];
    }
    
  }
}