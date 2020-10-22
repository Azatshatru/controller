<?php

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class CommonComponent extends Component
{
    public function common(){
       
       
        //setting
		$this->Settings = TableRegistry::get('Settings');
		$data['settings'] = $this->Settings->find()->first();
       
        return $data;
      
    }
}
