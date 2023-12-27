<?PHP

error_reporting(E_ERROR);
ini_set('display_errors', '1');

class Users extends DBMaster{
	
	public function createUser($void, $params){
        
        if ( !isset($params['usr']) || !isset($params['password']) ){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }
        
        $active = 0;
        if (isset($params['active'])){
            $active = $params['active'];
        }

        // include '../system/Security.php';
        $sec = new Security($this->validate_result);
        $createInfo = $sec->createUser($params['usr'],$params['password'], $active);
        if ($createInfo['result'] == 0){
            return $createInfo;
        }
        /*$createResult = $this->saveData('users', [
            'id' => $createInfo['data']['id'], 
            'name' => $params['name'], 
            'lastname' => $params['lastname']
        ], null);*/
        $createResult = $this->saveData('users', array_merge(['id' => $createInfo['data']['id']], $params), null);
        if (is_array($createResult)){
            return $createResult;
        }else{
           return ['result' => 1, 'data' => $createInfo['data']['id']]; 
        }
    }

    public function activateUser($void, $params){
        if ( !isset($params['user']) || !isset($params['code']) ){
            $con->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }

        include '../system/Security.php';
        $sec = new Security($this->validate_result);
        return $sec->activateUser($params['user'],$params['code']);
        
    }

    public function getUserInfo($void, $params){
      
        if ( !isset($params['id']) ){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }
        $usrInfo = [];
        if ($params['id'] == '_ME') {
            $params['id'] = $this->me;
        }
        $uquery = 'SELECT
                    `u`.*,
                    `sl`.`usr`,
                    `sl`.`active`,
                    `sl`.`ext_id`,
                    `w`.`ars`,
                    `w`.`usd`,
                    `w`.`cvu`,
                    `w`.`alias`
                    FROM `users` as `u` 
                    LEFT JOIN `system_login` as `sl` ON `sl`.`id` = `u`.`id` 
                    LEFT JOIN `wallet` as `w` ON `w`.`user` = `u`.`id`
                    WHERE `u`.`id`='.$params['id'];

		$result = $this->execQuery($uquery);

        if ($result['result'] == 0) {
            $this->auditData(2,self::SYSTEM_REQUEST_BADID,['params' => $params, 'table' => 'users'],null);
            return [
                        'result' => 0,
                        'errorcode' => self::SYSTEM_REQUEST_BADID,
                        'message' => 'The user does not exist'
                    ]; 
        }

        if ($result['records'][0]['ext_id'] == null) {
            $result['records'][0]['external'] = false;
        } else {
            $result['records'][0]['external'] = true;
        }

        $result['records'][0]['permissions'] = [];
        
        if ($result['records'][0]['rol'] !== null) {
        
            $query = "SELECT * 
                FROM `permissions` WHERE `rol`=".$result['records'][0]['rol'];

            $permissions = $this->execQuery($query);
            
            foreach($permissions['records'] as $record){
                $values = explode(',', $record['matrix']);
                $result['records'][0]['permissions'][$record['entity']] = [];
                foreach($values as $permissionValue) {
                    $result['records'][0]['permissions'][$record['entity']] []= intval($permissionValue); 
                }
            }
        }

        return [
            'result' => 1,
            'data' => $result['records'][0]
        ];

    }

    function getOne($table, $params){
        if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}
		if ( !isset($params['id']) ){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }
		
		$query = 'SELECT `u`.*, `sl`.`usr`, `sl`.`active`, `sl`.`ext_id` FROM `users` as `u` LEFT JOIN `system_login` as `sl` ON `sl`.`id` = `u`.`id` WHERE `u`.`id`='.$params['id'];

		$result = $this->execQuery($query);
		
        return $result;
    }

    function find($table, $fieldList){
       $fieldList['joins'] = [
            [
                'field' => ['active', 'created'],
                'table' => 'system_login',
                'on' => ['id', 'id']
            ],
            [
                'field' => ['name'],
                'alias' => ['rol_name'],
                'table' => 'roles',
                'on' => ['id', 'rol']
            ]
           ];
        return parent::find($table, $fieldList);
    }

    function save($table, $fieldList){
        /*$memberlist = $fieldList['teams'];
        unset($fieldList['teams']);*/
        
        $error = '';

        if (!isset($fieldList['id']) || empty($fieldList['id'])){
            $result = $this->createUser($table, $fieldList);
        }else{ 
            if ($fieldList['id'] == '_ME') {
                $fieldList['id'] = $this->me;
            }
        
            if (isset($fieldList['password']) && $fieldList['password'] != ''){
                include '../system/Security.php';
                $sec = new Security($this->validate_result);
                $sec->setPasword($fieldList['id'], $fieldList['password']);
                unset($fieldList['password']);
            }
            if (isset($fieldList['active'])) {
                $active = $fieldList['active'];
                $this->saveData('system_login',['active' => $active], $fieldList['id']);
            }
            unset($fieldList['active']);
            unset($fieldList['usr']);
            unset($fieldList['ext_id']);

            if (isset($fieldList['tag'])) {
                $fieldList['tag'] = strtoupper(trim($fieldList['tag']));
                if ($fieldList['tag'] != '') {
                    $tagExists = $this->execQuery('SELECT * FROM `users` WHERE `tag` LIKE "'.$fieldList['tag'].'" AND `id` != '.$fieldList['id'])['records'];
                    if (count($tagExists) > 0) {
                        unset($fieldList['tag']);
                        $error = 'El tag ya está en uso';
                    }
                }
            }

            $result = parent::save($table, $fieldList);
        }

        if ($result['result'] == 0 ){
			return $result;
		}
        
		return ["result" => "1", "data" => $result['data'], "error" => $error]; 

    }

    function changePassword($table, $fieldList){
        $usrInfo = [];
        foreach($this->connection->query("SELECT * FROM `system_login` WHERE `id` = '".$this->me."'") as $record){
            $usrInfo = $record;
        }
        if ( password_verify($fieldList['currentpassword'], $usrInfo['pass']) ){
            $sec = new Security($this->validate_result);
            $sec->setPasword($this->me, $fieldList['newpassword']);
            $result = ["result" => 1];
        } else {
            $result = [
                'result' => 0,                        
                'errorcode' => self::SYSTEM_LOGIN_BADPASS,
                'message' => 'La contraseña ingresada no coincide con la actual'
            ];
        }
        return $result;
    }

    function delete($table, $fieldList){
        $result = parent::delete($table, $fieldList);
        if ($result['result'] == '1'){
            $this->deleteData('system_login', $fieldList['id']);
        }
        return $result;
    }

    function updateRecoveryCode($table, $fieldList) {
        global $utils;

        $codeValues = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $recoveryCode = '';
        for($c=0;$c<5;$c++){
            $recoveryCode .= $codeValues[rand(0, strlen($codeValues)-1)];
        }
        if ($fieldList['id'] == '_ME') {
            $fieldList['id'] = $this->me;
        }
        $usrId = $this->saveData('system_login',[
            'usr' => $fieldList['email'], 
            'activationcode' => $recoveryCode, 
            'activationcodeexp' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ], $fieldList['id']);

        /*
        if (!class_exists('Notifications', false)) {
            include '../system/Notifications.php';
        }
        $notification = new Notifications($this->validate_result);
        

        $notification->send($table, [
            'to' => $fieldList['email'],
            'subject' => 'Código de validación Dolarizate',
            'title' => 'Este es tu código de validación para Dolarizate. Ingresalo en el cuadro de validación',
            'messaje' => $recoveryCode,
            'template' => 'recovery.html'
        ]);
        */
        $utils->connect([
            'url' => 'https://dolarizate.enterprise-its.com/be/api/exchange/notification',
            'method' => 'POST',
            'bodyType' => 'json',
            'to' => $fieldList['email'],
            'subject' => 'Código de validación Dolarizate',
            'title' => 'Este es tu código de validación para Dolarizate. Ingresalo en el cuadro de validación',
            'messaje' => $recoveryCode,
            'template' => 'recovery.html'
        ]);

        return [
            'result' => 1,
            'expiration' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ];
    }

    function validateRecoveryCode($table, $fieldList) {
        if ($fieldList['id'] == '_ME') {
            $fieldList['id'] = $this->me;
        }
        $usrInfo = [];
        foreach($this->connection->query("SELECT * FROM `system_login` WHERE `id` = '".$fieldList['id']."'") as $record){
            $usrInfo = $record;
        }
        if(strtotime($usrInfo['activationcodeexp']) < strtotime('now')){
            return [
                'result' => 0,
                'error' => 'Code expired'
            ];
        }
        if (strtoupper($fieldList['code']) != $usrInfo['activationcode']) {
            return [
                'result' => 0,
                'error' => 'Wrong code'
            ];
        }
        $this->saveData('users',[
            'validmail' => 1
        ], $fieldList['id']);
        
        return [
            'result' => 1
        ];
    }

    function generateCode($table, $params) {
        global $utils;
        $usrInfo = [];
        foreach($this->connection->query("SELECT * FROM `system_login` WHERE `usr` = '".$params['user']."'") as $record){
            $usrInfo = $record;
        }
        if (empty($usrInfo)) {
            return [
                'result' => 0,                        
                'errorcode' => self::SYSTEM_USER_ACTIVATION_BADUSER,
                'message' => 'The user does not exist'
            ];
        }
        $codeValues = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $recoveryCode = '';
        for($c=0;$c<5;$c++){
            $recoveryCode .= $codeValues[rand(0, strlen($codeValues)-1)];
        }
        $usrId = $this->saveData('system_login',['active' => '0', 'activationcode' => $recoveryCode, 'activationcodeexp' => date('Y-m-d H:i:s', strtotime('+1 day'))], $usrInfo['id'], true); 
        
        $mail = $utils->connect([
            'url' => 'https://dolarizate.enterprise-its.com/be/api/exchange/notifications/send',
            'method' => 'POST',
            'bodyType' => 'json',
            'body' => [
                'to' => $params['user'],
                'subject' => 'Código de validación Dolarizate',
                'title' => 'Este es tu código de validación para Dolarizate. Ingresalo en el cuadro de validación',
                'messaje' => $recoveryCode,
                'template' => 'recovery.html'
            ]
        ]);

        return [
            'result' => 1,
            'expiration' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'mail' => $mail
        ];
    }

    function passwordRecovery($table, $params) {
        if ( !isset($params['user']) || !isset($params['code']) ){
            $con->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }
        
        include_once '../system/Security.php';
        $sec = new Security($this->validate_result);
        
        $activation = $sec->activateUser($params['user'],$params['code']);
        if ($activation['result'] == 0) {
            return $activation;
        }
        if (isset($params['password']) && $params['password'] != ''){
            $sec->setPasword($activation['data'], $params['password']);
        }
        return $activation;
    }
 
}