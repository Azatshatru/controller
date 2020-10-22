<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\NotFoundException;
use Cake\Mailer\Email;

class JobApplicantsController extends AppController
{
    const FILE_PATH = 'jobs_applicants'.DS;
    
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['index','view']);
    }
    
    public function index($id = null)
    {
       $j_id = $id;
        $this->set('page_title', __('Careers'));
        $time = new Time();
        $conditions = [
            'Jobs.is_deleted' => false, 'Jobs.status' => true,
            'OR' => ['Jobs.from_date IS' => NULL, 'Jobs.from_date <=' => $time->format('Y-m-d')],
            'AND' => [
                'OR' => ['Jobs.to_date IS' => NULL, 'Jobs.to_date >=' => $time->format('Y-m-d')]
            ]
        ];
        
        $this->paginate = [
            'fields' => ['id', 'name', 'content', 'created'],
            'conditions' => $conditions,
            'order' => ['Jobs.lft' => 'ASC'],
        ];
        
        try
        {
            $paginateJobs = $this->paginate($this->JobApplicants->Jobs->find()); 
			
        }
        catch(NotFoundException $e)
        { 
            $totalRecord = $this->JobApplicants->Jobs->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $jobApplicant = $this->JobApplicants->newEntity();
        if($this->request->is('post'))
        {
            $jobApplicant2 = $this->request->withData('is_deleted', 0);
            $jobApplicant = $this->JobApplicants->patchEntity($jobApplicant, $jobApplicant2->getData());
            if(!$jobApplicant->errors())
            {
                $errors = [];
                if($this->request->getData('resume_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('resume_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('resume_file.name'), PATHINFO_EXTENSION));
                    $jobApplicant->resume_path = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$jobApplicant->resume_path);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $jobApplicant->resume_path = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$jobApplicant->resume_path);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('resume_file.tmp_name'), static::IMAGE_ROOT.$jobApplicant->resume_path);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload resume. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
					
                    if($this->JobApplicants->save($jobApplicant))
                    {
                        
                        if($this->coreVariable['CareerSenderEmail']!=''){
                            $sender = $this->coreVariable['CareerSenderEmail'];
                        }else{
                            $sender = $this->coreVariable['emailSenderEmail'];
                        }
                        $email = new Email();
                        $email->transport('gmail');
                        $email->emailFormat('html')
                                ->setTo($sender, $this->coreVariable['siteName'])
                                ->setSubject('Job Applicant enquiry for'.$this->coreVariable['siteName'])
                                ->template('career')
                                ->viewVars([
                                    'enquiry' => $jobApplicant,
                                    'sitename' => $this->coreVariable['siteName'],
                                    'imageRoot' => static::IMAGE_ROOT,
                                    'imageDir' => static::IMAGE_DIR
                                  ])
                                ->send();
                        $this->Flash->success(__('Resume has been submitted successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('Resume could not be submitted. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('Resume could not be submitted. Please see warning(s) below.'));
            }
        }
       
        $job = $this->JobApplicants->Jobs->find()
            ->where(['Jobs.is_deleted' => false, 'Jobs.status' => true,'Jobs.id'=>$j_id])
            ->andWhere(['OR' => ['Jobs.from_date IS' => NULL, 'Jobs.from_date <=' => $time->format('Y-m-d')]])
            ->andWhere(['OR' => ['Jobs.to_date IS' => NULL, 'Jobs.to_date >=' => $time->format('Y-m-d')]])
            ->order(['Jobs.lft' => 'ASC'])->first();
        
		
        $this->set(compact('paginateJobs', 'jobApplicant', 'job'));
       
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'JobApplicants');
        }
    }
	public function view()
    {
        $this->set('page_title', __('Careers'));
		$time = new Time();
        try
        {
            $jobs = $this->JobApplicants->Jobs->find()
            ->where(['Jobs.is_deleted' => false, 'Jobs.status' => true])
            ->andWhere(['OR' => ['Jobs.from_date IS' => NULL, 'Jobs.from_date <=' => $time->format('Y-m-d')]])
            ->andWhere(['OR' => ['Jobs.to_date IS' => NULL, 'Jobs.to_date >=' => $time->format('Y-m-d')]])
            ->order(['Jobs.lft' => 'ASC']);
			
        }
        catch(NotFoundException $e)
        { 
            return $this->redirect(['action' => 'view']);
        }
        
        
       
       $count = count($jobs->toArray());
       $this->set(compact('jobs','count'));
       
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'JobApplicants');
        }
    }
	
}
