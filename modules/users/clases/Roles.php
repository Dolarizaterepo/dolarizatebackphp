<?PHP

class Roles extends DBMaster{

  function save($table, $fieldList){
    if (isset($fieldList['defaultrol']) && $fieldList['defaultrol'] == true) {
      $this->execQuery('UPDATE `roles` SET `defaultrol`= 0');
    }
    
    $result = parent::save($table, $fieldList);
    $rolId = $result['data'];

    if (isset($fieldList['permissions'])) {
      $this->execQuery('DELETE FROM `permissions` WHERE `rol`='.$rolId);
      foreach($fieldList['permissions'] as $permission => $value) {
        foreach($value as $valueKey => $valueRecord) {
          if ($valueRecord === false) {
            $value[$valueKey] = 0;
          } else if ($valueRecord === true) {
            $value[$valueKey] = 1;
          }
        }
        $this->saveData('permissions',[
          'rol' => $rolId,
          'entity' => $permission,
          'matrix' => implode(',', $value)
        ], null);
      }
    }


    return $result;
  }

  public function getOne($table, $fieldList){
		$result = parent::getOne($table, $fieldList);
		
		$query = "SELECT * 
              FROM `permissions` WHERE `rol`=".$fieldList['id'];

		$permissions = $this->execQuery($query);

    $result['records'][0]['permissions'] = [];
    
    foreach($permissions['records'] as $record){
      $result['records'][0]['permissions'][$record['entity']] = explode(',', $record['matrix']);
    }

    return $result;
	}

}