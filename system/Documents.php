<?PHP

class Documents extends DBMaster{

    function save($table, $fieldList){
        
        $this->delete($table, ['entitytype' => $fieldList['entitytype'], 'entityid' => $fieldList['entityid']]);
        
        $fieldList['uploader'] = $this->me;
        $lenght = 13;
        $bytes = random_bytes(ceil($lenght / 2));
        $uid = substr(bin2hex($bytes), 0, $lenght);
        $fieldList['code'] = $uid;

        $saveItem = parent::save($table, $fieldList);

        if ($saveItem['result'] == 1 && isset($_FILES["file"])){
            if (!file_exists('repository/documents/'.$fieldList['entitytype'].'/')){
                mkdir('repository/documents/'.$fieldList['entitytype'].'/');
            }
            move_uploaded_file($_FILES["file"]["tmp_name"], 
                'repository/documents/'.
                $fieldList['entitytype'].'/'.
                $uid
            );
        }

        return $saveItem;
    }

    function delete($table, $fieldList){

        $documentExists = $this->getFilePath($table, $fieldList);
        
        if ($documentExists['result'] == 1) {
            if (file_exists($documentExists['data'])){
                unlink($documentExists['data']);
            }
            $this->execQuery('DELETE FROM `documents` WHERE `id`='.$documentExists['id']);
        }

        return $documentExists;

    }

    function getFilePath($table, $fieldList) {
      if (isset($fieldList['id'])){
          $documentExists = $this->execQuery("SELECT * FROM `documents` WHERE `id` = ".$fieldList['id']);
      } else {
          $documentExists = $this->execQuery("SELECT * FROM `documents` 
                                              WHERE 
                                                  `entitytype` = '".$fieldList['entitytype']."' AND
                                                  `entityid` = '".$fieldList['entityid']."'"
                                          );
      }
      
      if ($documentExists['result'] == 1 && count($documentExists['records']) > 0) {
          $filePath = 'repository/documents/'.
                          $documentExists['records'][0]['entitytype'].'/'.
                          $documentExists['records'][0]['code'];
          return ['result' => 1, 'data' => $filePath, 'id' => $documentExists['records'][0]['id']];
      } else {
        return ['result' => 0];
      }
    }

}