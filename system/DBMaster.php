<?PHP

require_once('../libs/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DBMaster{
	
	protected $connection = null;
	protected $systemConf = null;
	protected $validate_result = null;
	protected $me = null;
	protected $anonymous = true;

	const SYSTEM_LOGIN_BADREQUEST = -1;
	const SYSTEM_LOGIN_BADUSER = 1;
	const SYSTEM_LOGIN_BADPASS = 2;
	const SYSTEM_LOGIN_USRLOCK = 3;
	const SYSTEM_LOGIN_USRLOGIN = 4;
	const SYSTEM_USER_USERCREATED = 5;
	const SYSTEM_USER_USEREXISTS = 6;
	const SYSTEM_USER_NOLOGIN = 7;
	const SYSTEM_USER_ACTIVATION_BADUSER = 8;
	const SYSTEM_USER_ACTIVATION_BADCODE = 9;
	const SYSTEM_USER_ACTIVATION_CODEEXPIRED = 10;
	const SYSTEM_LOGIN_USRUNLOCK = 11;
	const SYSTEM_REQUEST_BADID = 12;
	const SYSTEM_ILLEGAL_ACTION = 13;
	
	function __construct($validate_result) {
		global $utils;
		if(!isset($utils)){
			$mainConf = json_decode(file_get_contents('../conf/main.json'),true);
			$env_conf = json_decode(file_get_contents('../conf/config_'.$mainConf['enviroment'].'.json'),true);			
			$this->systemConf = $env_conf;
		}else{
			$this->systemConf = $utils->system_config;
		}
		
		$this->validate_result = $validate_result;
		if ($validate_result['result'] == 1 && isset($validate_result['data']['id'])){
			$this->me = $validate_result['data']['id'];
			$this->anonymous = false;
		}

		$db = $this->systemConf['db']['name'];
		if (isset($this->systemConf['db']['instance'])){
			$db .= '_'.$this->systemConf['db']['instance'];
		}
		$host = $this->systemConf['db']['host'];
		$connResult = $this->connect("mysql:dbname=$db;host=$host",$this->systemConf['db']['user'],$this->systemConf['db']['password']);
		if ($connResult['result'] == 0){
			echo json_encode(['DBError' => $connResult['error']]);
			exit();
		}
		$this->afterCreate();
	}
	
	public function connect($dsn, $user, $pass){
		try{  
			$this->connection = new PDO($dsn, $user, $pass);
			return ['result' => 1];		
		} catch (PDOException $e) {
			$error = "Connection error: ".$e->getMessage();
			return ['result' => 0, 'error' => $error];
		}
	}

	public function afterCreate() {
		return true;
	}
	
	public function tableExists($table) {
		if ($this->connection == null){
			return ['result' => 0, 'error' => 'Connection not set'];
		}
		// Try a select statement against the table
		// Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
		try {
			$result = $this->connection->query("SELECT 1 FROM $table LIMIT 1");
		} catch (Exception $e) {
			// We got an exception == table not found
			return FALSE;
		}

		// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
		return $result !== FALSE;
	}
	
	public function execQuery($query){
		$records = [];
		try{
			foreach($this->connection->query($query, PDO::FETCH_NAMED) as $row){
				$records []=$row;
			}
		} catch (PDOException $e) {
			$error = "Query error: ".$e->getMessage();
			return ['result' => 0, 'error' => $error];
		}
		
		return ['result' => 1, 'records' => $records];
	}

	/*
		Recibe dos parametros:
		page -> con la pagina actual y
		r_page -> con la cantidad de registros por pagina
	*/
	public function paginate($params){
		$result = '';
		if (isset($params['page']) && isset($params['r_page']) && $params['r_page'] != -1){
			$records_by_page = $params['r_page'];
			$page = $params['page'];
			$result = ' limit '.( ($page - 1) * $records_by_page).', '.$records_by_page;
		}
		return $result;
	}

	/*
	Recibe un array de arrays de uno o dos registros. Ej:
		"order" :  [
					["id", "DESC"]
				   ]
	*/
	public function order($params){
		$result = '';
		if (isset($params['order'])){
			$orderItems = [];
			foreach($params['order'] as $order){
				$direction = 'ASC';
				if (isset($order[1])){
					$direction = $order[1];
				}
				$orderItems []= ' `'.$order[0].'` '.$direction;
			}
			if (count($orderItems) > 0){
				$result = ' ORDER BY '.implode(', ',$orderItems);
			}
		}
		return $result;
	}

	/*
		Recibe un array de objetos:
		"subqueries": [
			{
				"field" -> nombre del campo condicional
				"table" -> tabla a consultar
				"type" -> puede ser COUNT, MAX, MIN, si se omite es COUNT
				"alias" -> alias del campo, si se omite es field_table
				"mainfield" -> campo en la tabla principal, si se omite usa id
				"condition" -> si se define, agrega condiciones al WHERE del subquery,
				"custom" -> si no se define field, en este campo se define el subquery crudo
			}
		]
	*/
	public function getSubQuerys($params){
		$result = [];
		if (isset($params['subqueries'])){
			$orderTable = 1;
			foreach($params['subqueries'] as $subquery){
				if (isset($subquery['field']) && isset($subquery['table'])){
					$group = 'COUNT(*)';
					if (isset($subquery['type'])){
						$group = $subquery['type'];
					}
					$mainfield = 'id';
					if (isset($subquery['mainfield'])){
						$mainfield = $subquery['mainfield'];
					}
					$resultText = '(SELECT '.$group.' FROM `'.$subquery['table'].'` as `t'.$orderTable.'` WHERE `t'.$orderTable.'`.`'.$subquery['field'].'` = `main`.`'.$mainfield.'`';
					if (isset($subquery['condition'])){
						$resultText .= ' AND '.$subquery['condition'];
					}
					$resultText .= ') as ';
					if (isset($subquery['alias'])){
						$resultText .= '\''.$subquery['alias'].'\'';
					}else{
						$resultText .= '\''.$subquery['field'].'_'.$subquery['table'].'\'';
					}
					$orderTable ++;
					$result []= $resultText;
				} else if (isset($subquery['custom'])) {
					$result []= $subquery['custom'];
				}
			}
		}
		return $result;
	}

	/*
		Recibe un array de objetos:
		"joins": [
			{
				"field" -> Campo o campos que agregar,
				"table" -> Tabla que une,
				"r_table" -> Tabla con la que se une, si se omite toma la principal
				"alias" -> Alias de los campos (opcional)
				"on" -> Array [Parte 1 -> table, Parte 2 -> table2 o main],
				"direction" -> Dirección del JOIN, si se omite usa LEFT
			}
		]
	*/
	public function getJoins($params){
		$result = [
			'selects' => [], 
			'tables' => ''
		];
		if (isset($params['joins'])){
			$tables = [];
			foreach($params['joins'] as $join){
				if (
					isset($join['field']) &&
					isset($join['table']) &&
					isset($join['on'])
				){
					if (is_array($join['field'])){
						foreach($join['field'] as $field_key => $field_value){
							$fieldText = '`'.$join['table'].'`.`'.$field_value.'`';
							if (isset($join['alias'][$field_key])){
								$fieldText .= ' as \''.$join['alias'][$field_key].'\'';
							}
							$result['selects'][] = $fieldText;
						}
					}else{
						$fieldText = '`'.$join['table'].'`.`'.$join['field'].'`';
						if (isset($join['alias'])){
							$fieldText .= ' as \''.$join['alias'].'\'';
						}
						$result['selects'][] = $fieldText;
					}

					if (!in_array($join['table'], $tables)){
						$tables [] = $join['table'];
						$direction = 'LEFT';
						if (isset($join['direction'])){
							$direction = $join['direction'];
						}
						$remotetable = 'main';
						if (isset($jon['r_table'])){
							$remotetable = $jon['r_table'];
						}
						$result['tables'] .= ' '.$direction.' JOIN `'.$join['table'].'` ON `'.$join['table'].'`.`'.$join['on'][0].'` = `'.$remotetable.'`.`'.$join['on'][1].'`';
					}

				}
			}
		};
		return $result;
	}

	public function getAll($table, $params){
		if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}
		
		$select = ['`main`.*'];
		$paginate = '';
		$order = '';
		$tables = '';

		if (isset($params) && !empty($params)){			
			$paginate = $this->paginate($params);
			$order = $this->order($params);
			$joins = $this->getJoins($params);
			$select = array_merge($select, $joins['selects'], $this->getSubQuerys($params));
			$tables = $joins['tables'];
		}

		$query = 'SELECT '.implode(', ',$select).' FROM `'.$table.'` as `main`'.$tables.$order.$paginate;
		$result = $this->execQuery($query);
		
		if ($paginate != ''){
			$total = $this->execQuery('SELECT COUNT(*) as `total` FROM `'.$table.'` as `main`'.$tables);
			$result['total'] = $total['records'][0]['total'];
		}
		
		$result['query'] = $query;
		return $result;
	}
	
	public function getOne($table, $params){
		if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}
		if ( !isset($params['id']) ){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
        }
		
		$query = 'SELECT * FROM `'.$table.'` WHERE `id`='.$params['id'];

		return $this->execQuery($query);
		
	}

	public function find($table, $params){
		global $utils;
		
		if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}
		
		if (!isset($params)){
			return $this->getAll($table, $params);
		}

		$paginate = $this->paginate($params);
		$order = $this->order($params);
		$joins = $this->getJoins($params);
		$tables = $joins['tables'];
		if (isset($params['alias'])) {
			$selectBase = $params['alias'];
			unset($params['alias']);
		} else {
			$selectBase = ['`main`.*'];
		}
		$select = array_merge($selectBase, $joins['selects'], $this->getSubQuerys($params));
			

		if (isset($params['page']) && isset($params['r_page'])){
			unset($params['page']);
			unset($params['r_page']);
		}
		if (isset($params['order'])){
			unset($params['order']);
		}
		if (isset($params['subqueries'])){
			unset($params['subqueries']);
		}
		if (isset($params['joins'])){
			unset($params['joins']);
		}
		$filter = '';
		if (isset($params['filter'])){
			$filter = $utils->reverse_norm_entities($params['filter']);
			unset($params['filter']);
		}

		$condition = $this->buildcondition($params);
		if (!empty($condition)){
			$condition = ' WHERE '.$condition;
			if ($filter != ''){
				$condition .= ' AND '.$filter;
			}
		}else if($filter != ''){
			$condition = ' WHERE '.$filter;
		}

		$query = 'SELECT '.implode(', ',$select).' FROM `'.$table.'` as `main`'.$tables.$condition.$order.$paginate;
		$result = $this->execQuery($query);

		if ($paginate != ''){
			$total = $this->execQuery('SELECT COUNT(*) as `total` FROM `'.$table.'` as `main`'.$tables.$condition);
			$result['total'] = $total['records'][0]['total'];
		}
		
		$result['query'] = $query;

		return $result;
		
	}
	/*
	function saveForm
	params:
			$fieldList [Array][Tabla => [Campo => Valor]] Lista de tablas con sus respectivos campos			
	*/
	public function saveForm($void, $fieldList){
		$resultSet = [];
		
		foreach($fieldList as $table => $record){
			if (is_array($record)){
				if (isset($record['id'])){
					$id = $record['id'];
					unset($record['id']);
				}else{
					$id = '';
				}
				$resultSet[$table] = $this->saveData($table, $record, $id);
			}
		}
		
		return $resultSet;
		
	}
	
	/*
	function save
	params:
		   $table [string] Nombre de la tabla
		   $fieldList [Array][Campo => Valor] Lista de campos
		   $validation_data [Array] Token de validación
	*/
	function save($table, $fieldList){
			
		
		if (isset($fieldList['id'])){
			$id = $fieldList['id'];
			unset($fieldList['id']);
		}else{
			$id = '';
		}

		$relations = null;
		if (isset($fieldList['relations'])){
			$relations = $fieldList['relations'];
			unset($fieldList['relations']);
		}

		$result = $this->saveData($table, $fieldList, $id);
		if ( is_array($result) ){
			return $result;
		}else{
			if ($relations !== null) {
				$relationsResult = [];
				foreach($relations as $relation) {
					if (isset($relation['rtable'])) {
						if ($this->tableExists($table.'_'.$relation['rtable'])) {
							$ptable = $table.'_'.$relation['rtable'];
						} else if ($this->tableExists($relation['rtable'].'_'.$table)) {
							$ptable = $relation['rtable'].'_'.$table;
						} else {
							$ptable = null;
						}
					}
					if (isset($relation['ptable'])) {
						if ($this->tableExists($relation['ptable'])) {
							$ptable = $relation['ptable'];
						} else {
							$ptable = null;
						}
					}
					if ($ptable == null) {
						$relationsResult [] = [
							'request' => $relation,
							'result' => "Pivot table doesn't exists"
						];
						continue;
					}
					
					$data = [];
					if (isset($relation['data'])) {
						$data = $relation['data'];
						$left = array_key_first($data);
						if (strtolower($data[$left]) == '_id') {
							$data[$left] = $result;
						}
					}
					$mode = 'sync';
					if (isset($relation['mode'])) {
						$mode = $relation['mode'];
					}
					$relationsResult [] = [
						'request' => $relation,
						'result' => $this->updaterelations($ptable, $data, $mode)
					];
				}
				return ["result" => "1", "data" => $result, "relations" => $relationsResult];
			} else {
				return ["result" => "1", "data" => $result];
			}
		}
		
	}
	
	/*
	function saveData
	params:
			$table [string] Nombre de la tabla
			$fieldList [Array][Campo => Valor] Lista de campos
			$id [Integer][Opcional] Valor del id a actualizar
	*/
	public function saveData($table, $fieldList, $id = null, $noLog = false){
		if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}

		$columns = [];
		try{
			foreach($this->connection->query('SHOW COLUMNS FROM `'.$table.'`', PDO::FETCH_NAMED) as $row){
				$columns []=$row['Field'];
			}
		} catch (PDOException $e) {
			$error = "Query error: ".$e->getMessage();
			return ['result' => 0, 'error' => $error];
		}

		$fieldNames = array_keys($fieldList);
		foreach($fieldNames as $fieldName) {
			if (!in_array($fieldName, $columns)) {
				unset($fieldList[$fieldName]);
			} else if ($fieldList[$fieldName] == '_ME') {
				$fieldList[$fieldName] = $this->me;
			}
		}

		$snapshot = [];

		if ($id != null){
			//EDITAR
			$valores = implode(', ', array_map(
												function ($v, $k) { 
													if (($v === 'NULL' || is_null($v)) && !is_bool($v) ){
														return sprintf("`%s`= NULL", $k);
													}else{
														return sprintf("`%s`='%s'", $k, $v); 
													}
												},
												$fieldList,
												array_keys($fieldList)
											)
							  );
			$query = "UPDATE `".$table."` SET ".$valores." WHERE `id`='".$id."'";
			$snapshot = $this->execQuery("SELECT * FROM `".$table."` WHERE `id`='".$id."'")['records'][0];
		
		}else{
			//CREAR
			$campos = implode(', ', array_map(
												function ($v, $k) { return sprintf("`%s`", $k); },
												$fieldList,
												array_keys($fieldList)
											)
							  );
			$valores = implode(', ', array_map(
												function ($v, $k) { 
													if (($v === 'NULL' || is_null($v)) && !is_bool($v)){
														return 'NULL';
													}else{
														return sprintf("'%s'", $v); 
													}
												},
												$fieldList,
												array_keys($fieldList)
											)
							  );
							  
			$query = 'INSERT INTO `'.$table.'`('.$campos.') VALUES ('.$valores.')';
			
		}
			
		$data = $this->connection->prepare($query);
		$data->execute();
		if ($id != null){
			$resultId = $id;
		}else{
			$resultId = $this->connection->lastInsertId();
			if ($resultId == 0 && isset($fieldList['id'])) {
				$resultId = $fieldList['id'];
			}
		}
		
		/*
		if ($resultId == 0){
			echo 'Error: '.$query;
		}
		
		return ['result' => 0, 'data' => $query];*/
		
		//echo 'Error: '.$query."\n";
		if (!$noLog) {
			if (is_null($id)){
				$postData = $this->execQuery("SELECT * FROM `".$table."` WHERE `id`='".$resultId."'")['records'][0];
				$changeQuery = "INSERT INTO `system_changes`(`modiftable`, `action`, `changes`, `user`)
																						VALUES ('".$table."', 'insert', '".json_encode(['insertedData' => $postData])."',".$this->me.")";
				$this->connection->query($changeQuery);
			} else if (!empty($snapshot)) {
				$postData = $this->execQuery("SELECT * FROM `".$table."` WHERE `id`='".$id."'")['records'][0];
				$changes = [];
				foreach($snapshot as $field => $preData) {
					if ($field !== 'updated' && $postData[$field] != $preData) {
						$changes[$field] = ['pre' => $preData, 'post' => $postData[$field]];
					}
				}
				if (!empty($changes)) {
					$changeQuery = "INSERT INTO `system_changes`(`modiftable`, `action`, `changes`, `user`)
																							VALUES ('".$table."', 'update', '".json_encode($changes)."',".$this->me.")";
					$this->connection->query($changeQuery);
				}
			}
		}

		$validateColum = in_array('updated', $columns);
		if ($validateColum){
			$this->connection->query('UPDATE `'.$table.'` SET `updated`="'.date('Y-m-d H:i:s').'" WHERE `id`='.$resultId);
		}
		
		return $resultId;
	}

	/*
	function deleteData
	params:
			$table [string] Nombre de la tabla
			$id [Integer] Valor del id a borrar
	*/
	public function deleteData($table, $id){
		if (!$this->tableExists($table)){
			return ['result' => 0, 'error' => 'Table not exists'];
		}
		$result = true;
		$currentData = $this->execQuery("SELECT * FROM `".$table."` WHERE `id`='".$id."'")['records'];
		if (count($currentData) > 0) {
			$snapshot = $currentData[0];
			$changeQuery = "INSERT INTO `system_changes`(`modiftable`, `action`, `changes`, `user`)
																							VALUES ('".$table."', 'delete', '".json_encode(['deletedData' => $snapshot])."',".$this->me.")";
			$this->connection->query($changeQuery);
			
			$query = 'DELETE FROM `'.$table.'` WHERE `id`='.$id;
			$this->connection->query($query);
		} else {
			$result = false;
		}

		include_once '../system/Documents.php';
		$doc = new Documents($this->validate_result);
		$doc->delete($table, ['entitytype' => $table, 'entityid' => $id]);
		return $result;
	}

	/*
	function delete
	params:
		   $table [string] Nombre de la tabla
		   $fieldList [Array][Campo => Valor] Lista de campos || [Number] Id a borrar
		   $validation_data [Array] Token de validación
	*/
	function delete($table, $fieldList){
		if (!$this->checkValidation($this->validate_result)){
            $this->auditData(2,self::SYSTEM_USER_NOLOGIN,$this->validate_result,null);
            return [
                    'result' => 0,                        
                    'errorcode' => self::SYSTEM_USER_NOLOGIN,
                    'message' => 'Invalid token',
                    'token' => $this->validate_result
                    ];
		}
		
		if (is_array($fieldList)){
			if (isset($fieldList['id'])){
				$id = $fieldList['id'];
				unset($fieldList['id']);
			}else{
				$id = '';
			}
		}else{
			$id = $fieldList;
		}

		if ($id === '') {
			return ['result' => 0, 'error' => 'Bad request'];
		}
		if (is_array($id)){
			$result = [];
			foreach($id as $deleteId) {
				$result []= ['id' => $deleteId, 'result' => $this->deleteData($table, $deleteId)];
			}
			return ["result" => "1", "results" => $result];
		} else {
			$result = $this->deleteData($table, $id);
			if ( is_array($result) ){
				return $result;
			}else{
				return ["result" => "1", "data" => $result];
			}
		}
		
	}

	/*
	function updaterelations
	Borra las relaciones actuales y crea nuevas
	params:
		  $ptable [string] Nombre de la tabla pivot
			$data [Array] Valores a insertar
				=> Valores posibles: 
					[left => value, right => value] - Borra todos los que coinciden con left e inserta el par
					[left => value, right => [values]] - Borra todos los que coinciden con left e inserta los pares
					[left => value, ...rights => values] - Borra todos los que coinciden con left e inserta el valor de los pares
					[left => value, ...rights => [values]] - Borra todos los que coinciden con left e inserta todos los valores de los pares
			$mode [string] Tipo de actualización 
				=> Valores posibles:
						insert - Solo inserta, no borra
						delete - Solo borra, no inserta
						sync - Borra e inserta
	*/
	function updaterelations($ptable, $data, $mode){
		
		$dataKeys = array_keys($data);
		$left = $dataKeys[0];
		if ($mode == 'delete' || $mode == 'sync') {
			$this->connection->query('DELETE FROM `'.$ptable.'` WHERE `'.$left.'`='.$data[$left]);
		}

		if (($mode == 'insert' || $mode == 'sync') && count($dataKeys) > 1) {
			$counter = 0;
			$emptyArray = false;
			foreach($dataKeys as $dataKey) {
				if (is_array($data[$dataKey])){
					$counter = count($data[$dataKey]);
					if (empty($data[$dataKey])) {
						$emptyArray = true;
					}
				}
			}
			if (!$emptyArray) {
				if ($counter == 0) {
					$this->saveData($ptable, $data, null, true);
				} else {
					for($value=0; $value < $counter; $value++) {
						$dataToInsert = [
							$dataKeys[0] => $data[$dataKeys[0]]
						];
						foreach($dataKeys as $dataKeyIndex => $dataKey) {
							if ($dataKeyIndex !== 0) {
								if (is_array($data[$dataKey])){
									$dataToInsert[$dataKey] = $data[$dataKey][$value];
								} else {
									$dataToInsert[$dataKey] = $data[$dataKey];
								}
							}
						}
						$this->saveData($ptable, $dataToInsert, null, true);
					}
				}
			}
		}
	}

	/*
	function auditData
	params:
		$auditLevel [Integer] Nivel mínimo de registro
		$eventType [Integer] Tipo de evento
		$data [Array] Información a registrar
		$usr [Integer][Opcional] Id del usuario que generó el evento
	*/
	public function auditData($auditLevel, $eventType, $data, $usr = null){
		if ($this->systemConf['api']['loglevel'] >= $auditLevel){
			
			$origin = '';

			foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
				if (array_key_exists($key, $_SERVER) === true){
					foreach (explode(',', $_SERVER[$key]) as $ip){
						$ip = trim($ip); // just to be safe

						if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
							$origin = $ip;
						}
					}
				}
			}			

			$payload = [
						'type' => $eventType,
						'data' => json_encode($data),
						'origin' => $origin
			];
			if ($usr != null){
				$payload['usr'] = $usr;
			}
			$this->saveData('system_audit', $payload, null);
		}
	}
	
	/*
	function checkValidation
	params:
		validation_data [Array] información de validación
	*/
	function checkValidation($validation_data){
		if ($validation_data == '' || !isset($validation_data['result']) || $validation_data['result'] != 1){
			return false;
		}else{
			return true;
		}
	}
	/*
	function buildcondition
	params:
		$queryParams [Array] array condicional
	*/
	function buildcondition($queryParams){
		return implode(' AND ', array_map(
												function ($v, $k) { 
													if ($v == '_ME') {
														$v = $this->me;
													}
													$lastChar = substr($k, -1);
													if ($lastChar != '=' && $lastChar != '>' && $lastChar != '<'){
														if ($lastChar == ')' || $lastChar == '`'){
															$mask = "%s ='%s'";	
														}else{
															$mask = "`%s`='%s'";
														}
														if (strtolower($v) == 'null'){
														  if ($lastChar == ')' || $lastChar == '`'){
															$mask = "%s IS NULL";	
														  }else{
															$mask = "`%s` IS NULL";
														  }	
														}
														return sprintf($mask, $k, $v); 
													}else{
														$mask = "`%s`%s'%s'";
														if (substr($k, 0, 1) == '(' || substr($k, 0, 1) == '`'){
															$mask = "%s %s'%s'";	
														}
														$fullCond = substr($k, -2);
														if ($fullCond == '>=' || $fullCond == '<=' || $fullCond == '!='){
															$k = substr($k, 0, -2);
															return sprintf($mask, $k, $fullCond,$v);
														}else if ($fullCond == '%='){
															$k = substr($k, 0, -2);
															return sprintf($mask, $k, 'LIKE',$v);
														}
														else{
															$k = substr($k, 0, -1);
															return sprintf($mask, $k, $lastChar,$v);
														}
													}
												},
												$queryParams,
												array_keys($queryParams)
											)
						);
	}
	
	function upload($table, $params){
		if ( !isset($_FILES['file']) ){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
		}
		if (!getimagesize($_FILES["file"]["tmp_name"])){
            $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Wrong file'];
		}

		if (move_uploaded_file($_FILES["file"]["tmp_name"], 'repository/'.$params['repository'].'/'.$params['name'])) {
			return ['result' => 1, 'data' => 'File uploaded'];
		} else {
			return ['result' => 0, 'error' => 'Error uploading file'];
		}

	}

	function fileRemove($table, $params){
		if (!isset($params['repository']) || !isset($params['name'])){
			 $this->auditData(1,self::SYSTEM_LOGIN_BADREQUEST,$params,null);
            return ['result' => 0, 'error' => 'Bad request'];
		}
		if (unlink('repository/'.$params['repository'].'/'.$params['name'])){
			return ['result' => 1, 'data' => 'File removed'];
		} else {
			return ['result' => 0, 'error' => 'Error removing file'];
		}
	}
	
	function exportToExcel($table, $params){
	  if (isset($params['find'])) {
			$data = $this->find($table, $params['find']);
		} else {
			$data = $this->getAll($table, null);
		}
	  if ($data['result'] == 0){
		return $data;
	  }
	  $spreadsheet = new Spreadsheet();
	  $sheet = $spreadsheet->getActiveSheet();
	  
	  $header = [];
	  foreach($params['fields'] as $field){
			if ($field['field'] !== 'image'){
				$header []= html_entity_decode($field['label']);
			}
	  }


	  $spreadsheet->getActiveSheet()
            ->fromArray(
                $header,   // The data to set
                NULL,        // Array values with this value will not be set
                'A1'         // Top left coordinate of the worksheet range where
                            //    we want to set these values (default is A1)
            );

	  $row = 2;
	  $tf= ['No', 'Sí'];

	  foreach($data['records'] as $record){
	    $rowData = [];
			foreach($params['fields'] as $field){
				if (!isset($field['mask']) || html_entity_decode($field['mask'])[0] == '<'){
					if (isset($field['type']) && $field['type'] == 'enum'){
						$rowData []= html_entity_decode($field['list'][intval($record[$field['field']])]['label']);
					}else if (isset($field['type']) && $field['type'] == 'bool'){
						$rowData []= $tf[intval($record[$field['field']])];
					}else if (isset($field['type']) && $field['type'] == 'avatars') {
						$recordItems = [];
						foreach($record[$field['field']] as $avatarField) {
							$recordItems []= html_entity_decode($avatarField['name']).' '.html_entity_decode($avatarField['lastname']);
						}
						$rowData []= implode(', ', $recordItems);
					} else if($field['field'] !== 'image'){
						$rowData []= html_entity_decode($record[$field['field']]);
					}
				} else {
					$field['mask'] = html_entity_decode($field['mask']);
					$maskParts = preg_split('/({|})/',$field['mask'],null,PREG_SPLIT_DELIM_CAPTURE);
					
					if (count($maskParts) == 1) {
									$rowData [] = $field['mask'];
								} else {
									for (
						$maskpartsindex = 0;
											$maskpartsindex < count($maskParts);
											$maskpartsindex ++
											) {
												if ($maskParts[$maskpartsindex] == '{') {
														$maskParts[$maskpartsindex + 1] = $record[$maskParts[$maskpartsindex + 1]];
												}
											}
											$mergeMask = implode('', $maskParts);
						$rowData [] = preg_replace('/({|})/','', $mergeMask);
								}
				}
			}
		
			$spreadsheet->getActiveSheet()
							->fromArray(
									$rowData,   // The data to set
									NULL,        // Array values with this value will not be set
									'A'.$row         // Top left coordinate of the worksheet range where
															//    we want to set these values (default is A1)
							);
			$row ++;
	  }

		if (!isset($params['json']) || $params['json'] == false) {
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment;filename="myfile.xlsx"');
			header('Cache-Control: max-age=0');
		}

		ob_start();
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		 
		if (!isset($params['json']) || $params['json'] == false) {
			exit();
		} else {
      $xlsdata = ob_get_contents();
			ob_end_clean();
			return ['result' => 1, 'data' => "data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,".base64_encode($xlsdata)];
		}
	}
}