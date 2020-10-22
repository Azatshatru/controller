<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Mailer\Email;
use Cake\Network\Exception\NotFoundException;

class EnquiriesController extends AppController
{
	const FILE_PATH = 'enquiry'.DS;
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['index','enquiry','callback','subscribe','volunteer','donation']);
    }
    
    public function index($slug = null)
    {
        $this->set('page_title', __('Contact us'));
        
        try {
            $this->loadModel('Pages');
            $page_content = $this->Pages->find()
                            ->select(['id','name','heading','content','parent_id'])
                            ->where(['Pages.slug' => 'contact','Pages.is_deleted' => false])
                            ->first();
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
        
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
            $enquiry2 = $this->request->withData('is_deleted', 0)
                     ->withData('type', 'contact');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData());
          
            if(!$enquiry->errors())
            {
                if($this->Enquiries->save($enquiry))
                {
                    $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                          ->setTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['siteName'])
                          ->setSubject('Enquiry for '.$this->coreVariable['siteName'])
                          ->template('enquiry')
                          ->viewVars([
                              'enquiry' => $enquiry,
                            ])
                          ->send();
                    $this->Flash->success(__('Enquiry form has been submitted successfully.'));
                    return $this->redirect($this->_redirectUrl());
                }
                else
                {
                    $this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
                }
            }
            else
            {
                $this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
            }
        }
        $this->loadModel('Settings');
        $setting = $this->Settings->find()
                            ->first();
		$this->loadModel('Courses');
        $coursesList = $this->Courses->find('list')
                    ->contain(['Institutes','LevelCodes'])
                    ->where(['Courses.is_deleted' => false,'Courses.status' => true,'Institutes.is_deleted' => false,'Institutes.status' => true,'LevelCodes.is_deleted' => false,'LevelCodes.status' => true]);
        $this->set(compact('page_content', 'enquiry','setting', 'coursesList'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Enquiries');
        }
    }
    
    public function callback(){ 
        $this->autoRender = false;
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
            $enquiry2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'contact');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData());
            
            if(!$enquiry->errors())
            {
                if($detail = $this->Enquiries->save($enquiry))
                {
					
					$enquiry = $this->Enquiries->get($detail->id);
					$sendTo = array();
					if(strpos($this->coreVariable['emailSenderEmail'], ',') !== false) 
					{ 
						$email1 = explode(',',$this->coreVariable['emailSenderEmail']);
						foreach($email1 as $email2)
						{
							$sendTo[] = $email2;
						}
					}else{
						$sendTo[] = $this->coreVariable['emailSenderEmail'];
					}
                    $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                      ->setTo($sendTo, $this->coreVariable['siteName'])
                      ->setSubject('Request Callback enquiry from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'])
                      ->template('enquiry')
                      ->viewVars([
                          'enquiry' => $enquiry,
                        ])
                      ->send();
                    $msg = '<div class="note note-success success" onclick="this.classList.add("hidden")" style="color:green;">Enquiry form has been submitted successfully.</div>';
                }
                else
                {
                    $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Enquiry form could not be submitted. Please see warning(s) below.</div>';
                }
            }
            else
            {
                $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Enquiry form could not be submitted. Please see warning(s) below.</div>';
            }
        }
        echo $msg;exit;
        $this->set(compact('msg'));
        $this->viewBuilder()->setLayout('ajax');
        
    }
    
    public function subscribe(){
        $this->autoRender = false;
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
            
            $check_enq = $this->Enquiries->find()
                        ->where(['Enquiries.email' => $this->request->getData('email'),'Enquiries.is_deleted' => false])
                        ->first();
            
            if(empty($check_enq)){
            
            $enquiry2 = $this->request->withData('is_deleted', 0)
                                       ->withData('type', 'subscribe');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData(),[
                'validate' => 'subscribe'
            ]);
			
            if(!$enquiry->errors())
            {
				
                if($this->Enquiries->save($enquiry))
                {
                    $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                          ->setTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                          ->setSubject('Subscription for '.$this->coreVariable['siteName'])
                          ->template('subscribe')
                          ->viewVars([
                              'enquiry' => $enquiry,
                            ])
                          ->send();
                    
                    $msg =  '<div class="note note-success show" role="alert" id="success_message"  onclick="this.classList.add("hidden")" style="color:green;">Thanks! You have successfully subscribed.</div>';
                }
                else
                {   pr($enquiry);exit;
                    $msg =  '<div class="note note-danger show" role="alert" id="error_message"  onclick="this.classList.add("hidden");" style="color:#d82424;">Failed</div>';
                }
            }
            else
            {
                 $msg =  '<div class="note note-danger show" role="alert" id="error_message"  onclick="this.classList.add("hidden");" style="color:#d82424;">Failed</div>';
            }
            }
            else
            {
                 $msg =  '<div class="note note-danger show" role="alert" id="error_message"  onclick="this.classList.add("hidden");" style="color:#d82424;">Email Address is already subscribed!</div>';
            }
        echo $msg;
        $this->set(compact('msg'));
        $this->viewBuilder()->setLayout('ajax');
        }
      
    }
    
    public function enquiry(){
        $this->autoRender = false;
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
            $enquiry2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'course');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData());
         
            if(!$enquiry->errors())
            {
                if($this->Enquiries->save($enquiry))
                {
                    $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                          ->setTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['siteName'])
                          ->setSubject('Admission Enquiry for'.$this->coreVariable['siteName'])
                          ->template('admission_enquiry')
                          ->viewVars([
                              'enquiry' => $enquiry,
                            ])
                          ->send();
                    $msg = '<div class="note note-success success" onclick="this.classList.add("hidden")" style="color:green;">Enquiry form has been submitted successfully.</div>';
                }
                else
                {
                    $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Enquiry form could not be submitted. Please see warning(s) below.</div>';
                }
            }
            else
            {
                $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Enquiry form could not be submitted. Please see warning(s) below.</div>';
            }
        }
        echo $msg;
        $this->set(compact('msg'));
        $this->viewBuilder()->setLayout('ajax');
        
    }
	
	public function volunteer(){ 
	
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
			
            $enquiry2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'volunteer');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData());
			
            if(!$enquiry->errors())
            {
                if($detail = $this->Enquiries->save($enquiry))
                {
					
					$enquiry = $this->Enquiries->get($detail->id);
					$sendTo = array();
					if(strpos($this->coreVariable['emailSenderEmail'], ',') !== false) 
					{ 
						$email1 = explode(',',$this->coreVariable['emailSenderEmail']);
						foreach($email1 as $email2)
						{
							$sendTo[] = $email2;
						}
					}else{
						$sendTo[] = $this->coreVariable['emailSenderEmail'];
					}
                    $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                      ->setTo($sendTo, $this->coreVariable['siteName'])
                      ->setSubject('Request volunteer enquiry from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'])
                      ->template('volunteer')
                      ->viewVars([
                          'enquiry' => $enquiry,
                        ])
                      ->send();
                    $this->Flash->success(__('Enquiry form has been submitted successfully.'));
                    return $this->redirect(['action' => 'volunteer']);
					
                }
                else
                {
                    $this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
                }
            }
            else
            {
               $this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
            }
        }
        $this->set(compact('enquiry'));
    }
	public function donation(){ 
	
        $enquiry = $this->Enquiries->newEntity();
        if($this->request->is('post'))
        {
			
            $enquiry2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'donation');
            $enquiry = $this->Enquiries->patchEntity($enquiry, $enquiry2->getData());
			
            if(!$enquiry->errors())
            {
				$errors = [];
                if($this->request->getData('material_image.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('material_image.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('material_image.name'), PATHINFO_EXTENSION));
                    $enquiry->material_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$enquiry->material_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $enquiry->material_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$enquiry->material_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('material_image.tmp_name'), static::IMAGE_ROOT.$enquiry->material_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload gallery photo. Please try again.');
                    }
                }
				if(empty($errors))
                {
					if($detail = $this->Enquiries->save($enquiry))
					{
						
						$enquiry = $this->Enquiries->get($detail->id);
						$sendTo = array();
						if(strpos($this->coreVariable['emailSenderEmail'], ',') !== false) 
						{ 
							$email1 = explode(',',$this->coreVariable['emailSenderEmail']);
							foreach($email1 as $email2)
							{
								$sendTo[] = $email2;
							}
						}else{
							$sendTo[] = $this->coreVariable['emailSenderEmail'];
						}
						$imageRoot = static::IMAGE_ROOT;
						$email = new Email();
						$email->transport('gmail');
						$email->emailFormat('html')
						  ->setTo($sendTo, $this->coreVariable['siteName'])
						  ->setSubject('Donation detail from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'])
						  ->template('donation')
						  ->viewVars([
							  'enquiry' => $enquiry,
							  'imageRoot'=>$imageRoot
							])
						  ->send();
						$this->Flash->success(__('Enquiry form has been submitted successfully.'));
						return $this->redirect(['action' => 'donation']);
						
					}
					else
					{
						$this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
					}
				}
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
               $this->Flash->error(__('Enquiry form could not be submitted. Please see warning(s) below.'));
            }
        }
        $this->set(compact('enquiry'));
    }
}
