<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Network\Exception\NotFoundException;
use Cake\Filesystem\File;
use Cake\Mailer\Email;

class AlumnusController extends AppController {
	const FILE_PATH = 'alumani'.DS;
    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index']);
    }

    public function index()
    {
        $this->set('page_title', __('Alumni'));
       
        $alumni = $this->Alumnus->newEntity();
		if($this->request->is('post'))
        { 
	        
			$alumni2 = $this->request->withData('is_deleted', 0);
			$alumni2 = $this->request->withData('status', 1);
            $alumni = $this->Alumnus->patchEntity($alumni, $alumni2->getData());
			
            if(!$alumni->errors())
            {
				
                $errors = [];
                if($this->request->getData('student_img.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('student_img.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('student_img.name'), PATHINFO_EXTENSION));
                    $alumni->student_img = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$alumni->student_img);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $alumni->student_img = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$alumni->student_img);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('student_img.tmp_name'), static::IMAGE_ROOT.$alumni->student_img);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload alumni image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Alumnus->save($alumni))
                    {
						
						$email = new Email();
						$email->transport('gmail');
						$email->emailFormat('html')
							  ->setTo($alumni->email, $this->coreVariable['siteName'])
							  ->setSubject('for Alumni, '.$this->coreVariable['siteName'])
							  ->template('alumni')
							  ->send();
						
						$this->Flash->success(__('The alumni has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The alumni could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The alumni could not be added. Please see warning(s) below.'));
            }
			
		} 
		
		$this->set(compact('alumni','sessionArr'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Alumnus');
        }
    }
}
