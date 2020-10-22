<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Mailer\Email;
use Cake\Network\Exception\NotFoundException;

class ContactsController extends AppController
{
	const FILE_PATH = 'enquiry'.DS;
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['getTouch','visit','index']);
    }
    
   
    
    
	public function getTouch(){ 
	
        $contact = $this->Contacts->newEntity();
        if($this->request->is('post'))
        {
			
            $contact2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'connect');
            $contact = $this->Contacts->patchEntity($contact, $contact2->getData());
			
            if(!$contact->errors())
            {
				
				if($detail = $this->Contacts->save($contact))
				{
					
					$contact = $this->Contacts->get($detail->id);
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
					/* $email = new Email();
					$email->transport('gmail');
					$email->emailFormat('html')
					  ->setTo($sendTo, $this->coreVariable['siteName'])
					  ->setSubject('GET IN TOUCH from '.@ucfirst($contact->name).' for '.$this->coreVariable['siteName'])
					  ->template('touch')
					  ->viewVars([
						  'enquiry' => $contact
						])
					  ->send(); */
					  	$to = $this->coreVariable['emailSenderEmail'];
                        $subject = 'GET IN TOUCH from '.@ucfirst($contact->name).' for '.$this->coreVariable['siteName'];
                        $txt = ' <table border="0" cellpadding="0" cellspacing="1" style="font:normal 13px arial; width:100%; max-width:750px;">
<tr><td colspan="2" align="center" style="border:1px solid #c59d45;"></td></tr></br>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Name</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$contact->name.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Email</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$contact->email.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Phone no.</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$contact->phone.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Age</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$contact->age.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Address</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$contact->address.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Profession</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$contact->profession.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Sex</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$contact->sex.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>How did you hear about ETASHA? Image</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$contact->about_first.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Would you like to receive our Newsletter Image</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$contact->about_second.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Comment</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$contact->comment.'
	</td>
</tr>
</table>';
                       
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        
                        
                        $headers .= 'From: <etashasociety.org@gmail.com>' . "\r\n";
                        $headers .= 'Cc: '. $contact->email. "\r\n";

                        if(mail($to,$subject,$txt,$headers))
                        {
                           $msg = '<div class="note note-success success" onclick="this.classList.add("hidden")" style="color:green;">Contact Enquiry form has been submitted successfully.</div>';
                        }
                        else{
                           $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Contact Enquiry form could not be submitted. Please see warning(s) below.</div>';
                        }
					
					
				}
				else
				{
					$msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Contact Enquiry form could not be submitted. Please see warning(s) below.</div>';
                }
				
			}
            else
            {
               $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Contact Enquiry form could not be submitted. Please see warning(s) below.</div>';
            }
        }
        echo $msg; exit;
        $this->set(compact('msg'));
        $this->viewBuilder()->setLayout('ajax');
    }
	
	public function visit(){ 
	
        $visit = $this->Contacts->newEntity();
        if($this->request->is('post'))
        {
			
            $visit2 = $this->request->withData('is_deleted', 0)
                            ->withData('type', 'visit');
            $visit = $this->Contacts->patchEntity($visit, $visit2->getData());
			$visit->visit_date = $visit->year.'-'.$visit->month.'-'.$visit->day;
            if(!$visit->errors())
            {
				
				if($detail = $this->Contacts->save($visit))
				{
					 
					$visit = $this->Contacts->get($detail->id);  
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
				/*	$email = new Email();
					$email->transport('gmail');
					$email->emailFormat('html')
					  ->setTo($sendTo, $this->coreVariable['siteName'])
					  ->setSubject('Plan a visit from '.@ucfirst($visit->name).' for '.$this->coreVariable['siteName'])
					  ->template('plan')
					  ->viewVars([
						  'enquiry' => $visit
						])
					  ->send(); */
				$to = $this->coreVariable['emailSenderEmail'];
                        $subject = 'GET IN TOUCH from '.@ucfirst($visit->name).' for '.$this->coreVariable['siteName'];
                        $txt = ' <table border="0" cellpadding="0" cellspacing="1" style="font:normal 13px arial; width:100%; max-width:750px;">
<tr><td colspan="2" align="center" style="border:1px solid #c59d45;"></td></tr></br>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Name</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$visit->name.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Organisation</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$visit->organisation.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Profession</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.$visit->profession.'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Visit Date</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">'.date("d-m-y",strtotime($visit->visit_date)).'</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Visit Purpose</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$visit->visit_purpush.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>How did you hear about ETASHA?</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$visit->about_first.'
	</td>
</tr>
<tr>
    <td style="border:1px solid #e8e8e8; padding:10px; color:#444; background:#f9f9f9;"><strong>Comment</strong></td>
    <td style="border:1px solid #e8e8e8; padding:10px;">
	'.$visit->comment.'
	</td>
</tr>
</table>';
                       
                        $headers = "MIME-Version: 1.0" . "\r\n";
                        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                        
                        
                        $headers .= 'From: <etashasociety.org@gmail.com>' . "\r\n";
                       

                        if(mail($to,$subject,$txt,$headers))
                        {
                          $msg = '<div class="note note-success success" onclick="this.classList.add("hidden")" style="color:green;">Plan to visit Enquiry form has been submitted successfully.</div>';
                        }
                        else{
                           $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Plan to visit Enquiry form could not be submitted. Please see warning(s) below.</div>';
                        }	 
					
					
				}
				else
				{
					$msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Plan to visit Enquiry form could not be submitted. Please see warning(s) below.</div>';
                }
				
			}
            else
            {
               $msg = '<div class="note note-danger" onclick="this.classList.add("hidden");" style="color:#d82424;">Plan to visit Enquiry form could not be submitted. Please see warning(s) below.</div>';
            }
        }
        echo $msg; exit;
        $this->set(compact('msg'));
        $this->viewBuilder()->setLayout('ajax');
    }
}
