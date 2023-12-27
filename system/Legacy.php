<?PHP

class Legacy extends DBMaster {
  protected $client = null;
  
  public function afterCreate() {
	  if (!$this->anonymous) {
		$userinfo = $this->execQuery('SELECT 
										`u`.* 
									FROM 
										`usuarios` as `u` 
									WHERE 
										`u`.`usuario_id` ='.$this->me)['records'][0];
		$this->client = $userinfo['usuario_cliente_id'];
	  }
  }
}