<?PHP

class Audit extends DBMaster{

  public function find($table, $fieldList){
    return parent::find('system_changes', $fieldList);
  }

}