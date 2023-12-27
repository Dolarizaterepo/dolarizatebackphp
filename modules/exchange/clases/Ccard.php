<?PHP

class Ccard extends DBMaster{

  function getbalance($table, $fieldList){
    $lastpaid = $this->execQuery('SELECT * FROM `ccard` WHERE `status` = 1 ORDER BY `updated` DESC LIMIT 1');
    $from = '';
    if (!empty($lastpaid['records'])) {
      $form = ' AND `created` > "'.$lastpaid['records'][0]['amount'].'"';
    }
    $expenses = $this->execQuery("SELECT SUM(`value`) as `total` FROM `balance` WHERE `type` = 3".$form);
    if (empty($expenses['records'])) {
      $saldo = 0;
    } else {
      $saldo = floatval($expenses['records'][0]['total']) * -1;
    }
    return [
      'result' => 1,
      'lastpaid' => $form,
      'debt' => $saldo
    ];
  }
}