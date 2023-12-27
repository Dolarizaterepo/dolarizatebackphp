<?PHP

class Notifications extends DBMaster{

    public function send($table, $params){

        
        $para  = $params['to'];
		
		if (isset($params['subject'])) {
			$título = $params['title'];
		} else {
		    $título = $params['title'];
		}
        
		  $mensaje = file_get_contents('./repository/templates/'.$params['template']);
          $mensaje = str_replace('__BODY__',$params['messaje'], $mensaje);
          $mensaje = str_replace('__TITLE__',$params['title'], $mensaje);
		  
          // Para enviar un correo HTML, debe establecerse la cabecera Content-type
          $cabeceras  = 'MIME-Version: 1.0' . "\r\n";
          $cabeceras .= 'Content-type: text/html; charset=utf-8' . "\r\n";

          // Cabeceras adicionales
          $cabeceras .= 'From: ' . $this->systemConf['notification']['from'] . "\r\n";
          
          // Enviarlo
          $result = null;
          
          try {
            $result = mail($para, $título, $mensaje, $cabeceras);
          } catch (Exception $e) {
            var_dump($e->getMessage());
          }
          
          return $result;

    }
}