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
        $this->Auth->allow(['uplode','index','enquiry','callback','subscribe','volunteer','donation','test']);
    }
    
	public function uplode()
    {
		if($this->request->is('post'))
        {
			
			 if($this->request->getData('image.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image.name'), PATHINFO_FILENAME)); 
                    $fileExtension = strtolower(pathinfo($this->request->getData('image.name'), PATHINFO_EXTENSION)); 
                    $image1 = static::FILE_PATH.$fileName.'.'.$fileExtension;
                   
                    $file = new File(static::IMAGE_ROOT.$image1);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                   $i = 1;
                    while($file->exists())
                    {
                        $image1 = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$image1);
                    }
                    
                   $success = move_uploaded_file($this->request->getData('image.tmp_name'), static::IMAGE_ROOT.$image1);
				  
                    if(!$success)
                    {
                        echo 'error';
                    }
					else{
						echo $image1;
					} 
                }
		} 
		exit;
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
                    /* $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                          ->setTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                          ->setSubject('Subscription for '.$this->coreVariable['siteName'])
                          ->template('subscribe')
                          ->viewVars([
                              'enquiry' => $enquiry,
                            ])
                          ->send();
                    
                    $msg =  '<div class="note note-success show" role="alert" id="success_message"  onclick="this.classList.add("hidden")" style="color:green;">Thanks! You have successfully subscribed.</div>'; */
                    $to = $this->coreVariable['emailSenderEmail'];
                        $subject = 'Subscription for '.$this->coreVariable['siteName'];
                        $txt = ' <table border="0" cellpadding="0" cellspacing="1" style="font:normal 13px arial; width:100%; max-width:750px;">
<tr><td colspan="2" align="center" style="border:1px solid #c59d45;">Subscribe</td></tr></br>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Email</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->email.'</td>
</tr>
</table>';
                       
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        
                        
                        $headers .= 'From: <etashasociety.org@gmail.com>' . "\r\n";
                       

                        if(mail($to,$subject,$txt,$headers))
                        {
                           $msg = '<div class="note note-success show" role="alert" id="success_message"  onclick="this.classList.add("hidden")" style="color:green;">Thanks! You have successfully subscribed.</div>';
                        }
                        else{
                           $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Try Again.</div>';
                        }
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
			$enquiry->dob = $enquiry->year.'-'. $enquiry->month.'-'. $enquiry->day;
			$enquiry->submitted_date = date('Y-m-d',strtotime($enquiry->submitted_date));
			//pr($enquiry);exit;
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
                    /* $email = new Email();
                    $email->transport('gmail');
                    $email->emailFormat('html')
                      ->setTo($sendTo, $this->coreVariable['siteName'])
                      ->setSubject('Request volunteer enquiry from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'])
                      ->template('volunteer')
                      ->viewVars([
                          'enquiry' => $enquiry,
                        ])
                      ->send(); */
                    $to = $this->coreVariable['emailSenderEmail'];
                        $subject = 'Donation detail from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'];
                        $txt = ' <table border="0" cellpadding="0" cellspacing="1" style="font:normal 13px arial; width:100%; max-width:750px;">
<tr><td colspan="2" align="center" style="border:1px solid #c59d45;"></td></tr></br>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Name</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->name.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Email</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->email.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Phone no.</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->phone.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>D.O.B</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'. date("d-m-Y",strtotime($enquiry->dob)).'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Address</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->address.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Qualification</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->qualification.'</td>
</tr><tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>College/University</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->college.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Company, Designation (for working volunteers)</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->company_detail.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Area of interest</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->interest.'</td>
</tr><tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Specific Skills</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->skill.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Languages - Spoken / Written</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->language.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>No. of weeks you can volunteer</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->week_no.'</td>
</tr><tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Willings to travel / stay outsation Y/N</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->travel.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Email</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->email.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>The month and week you can begin (if selected)</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->begin.'</td>
</tr><tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Any past experience of interning Y/N</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->experience.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Organizations Name</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->organization.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Location</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->location.'</td>
</tr><tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Duration Of internship</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->internship.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Nature of internship</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->internship_nature.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Project</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->project.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>How did you hear about ETASHA?</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->about_etasha.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Submitted by</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->submitted_by.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Date</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.date("d-m-Y",strtotime($enquiry->submitted_date)).'</td>
</tr>
</table>';
                       
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        
                        
                        $headers .= 'From: <etashasociety.org@gmail.com>' . "\r\n";
                        $headers .= 'Cc: '. $enquiry->email. "\r\n";

                        if(mail($to,$subject,$txt,$headers))
                        {
                           $msg ="Suceesfully submited";
                        }
                        else{
                             $msg ="plase try again";
                        }
                   
					
                }
                else
                {
                     $msg ="plase try again";
                }
            }
            else
            {
                $msg ="plase try again";
            }
			echo $msg;
        }
		
		for($i=1;$i<=31;$i++){
			$days[$i] = $i;
		}
		for($ii=1;$ii<=12;$ii++){
			$month[$ii] = $ii;
		}
		for($iii=1965;$iii<=2020;$iii++){
			$year[$iii] = $iii;
		}
		
        $this->set(compact('enquiry','days','month','year'));
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
						$to = $this->coreVariable['emailSenderEmail'];
                        $subject = 'Donation detail from '.@ucfirst($enquiry->name).' for '.$this->coreVariable['siteName'];
                        $txt = ' <table border="0" cellpadding="0" cellspacing="1" style="font:normal 13px arial; width:100%; max-width:750px;">
<tr><td colspan="2" align="center" style="border:1px solid #c59d45;"></td></tr></br>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Name</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->name.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Email</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->email.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Phone no.</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->phone.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Donations Material</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->donations_material.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Drop & Pickup</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$enquiry->drop_pickup.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Material Image</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	<img src="'.$imageRoot.$enquiry->material_image.'" style="width:120px;height:120px;">
	</td>
</tr>
</table>';
                       
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        
                        
                        $headers .= 'From: <etashasociety.org@gmail.com>' . "\r\n";
                        $headers .= 'Cc: '. $enquiry->email. "\r\n";

                        if(mail($to,$subject,$txt,$headers))
                        {
                            $msg = 'Donation form has been submitted successfully.';
                        }
                        else{
                            $msg = 'Donation form could not be submitted.';
                        }
                        
						/* $imageRoot = static::IMAGE_ROOT;
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
						$msg = 'Donation form has been submitted successfully.';*/
						
					
				}
                else
                {
					$msg = implode('<br />', $errors);
                }
            }
            else
            {
				$msg = implode('Donation form could not be submitted. Please see warning(s) below.');
               
            }
			echo $msg;
        }
        $this->set(compact('enquiry','status'));
    }
    
    	public function test(){ 
        $msg = "First line of text\nSecond line of text";
        
        
        $msg = wordwrap($msg,70);
        
       
        if(mail("manoj@ifwworld.com","My subject",$msg))
        {
            echo '1';
    	}
    	else{
    	echo '0';
    	}
        		
        	
        exit;		
    }
}
