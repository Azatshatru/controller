<?php

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Network\Exception\ForbiddenException;
use Cake\Network\Exception\NotFoundException;
use Cake\View\Exception\MissingTemplateException;
use Cake\Utility\Hash;
use Cake\Filesystem\File;
use Cake\I18n\Time;

class HomeController extends AppController {

    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index','visitorCounter']);
    }

    public function index() 
	{
		
		$this->loadModel('Banners');
        $time = new Time();
        
        $banners = $this->Banners->find()
            ->select(['banner_image','name', 'alt_tag', 'url'])
            ->where(['Banners.is_deleted' => false, 'Banners.status' => true])
            ->andWhere(['OR' => ['Banners.from_date IS' => NULL, 'Banners.from_date <=' => $time->format('Y-m-d')]])
            ->andWhere(['OR' => ['Banners.to_date IS' => NULL, 'Banners.to_date >=' => $time->format('Y-m-d')]])
            ->order(['Banners.lft' => 'ASC'])
            ->limit(10);
      
		
       
        $imagePath = static::IMAGE_DIR;
        
        $this->set(compact('banners','imagePath'));
    }
	
	public function visitorCounter()
    {
        $file = new File(WWW_ROOT.'count.txt');
        $numberCounter = (int) $file->read();
        
        if($this->request->getCookie('isVisited'))
        { 
            $this->response = $this->response->withCookie('isVisited', [
                'value' => 'yes',
                'path' => '/',
                'httpOnly' => true,
                'secure' => false,
                'expire' => strtotime('+24 hours')
            ]);
        }
        else
        {
            $numberCounter++;
            $file->write($numberCounter);
            
            $this->response = $this->response->withCookie('isVisited', [
                'value' => 'yes',
                'path' => '/',
                'httpOnly' => true,
                'secure' => false,
                'expire' => strtotime('+24 hours')
            ]);
        }
        
        $this->set(compact('numberCounter'));
        
        $this->viewBuilder()->setLayout('ajax');
    }
	
}
