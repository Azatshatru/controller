<?php
/**
 * @author: Manoj Tanwar
 * @date: April 23, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class JobApplicantsController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        
        if(!$this->userAuthorized())
        {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }
    
    protected function _index()
    {
        $paramarters = [
            ['name', 'JobApplicants.name', 'LIKE', ''],
            ['email', 'JobApplicants.email', 'LIKE', ''],
            ['mobile', 'JobApplicants.mobile', 'LIKE', ''],
            ['job_id', 'JobApplicants.job_id', 'EQUALS', ''],
            ['job_type', 'JobApplicants.job_type', 'EQUALS', ''],
            ['experience', 'JobApplicants.experience', 'EQUALS', ''],
            ['created_from', 'JobApplicants.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'JobApplicants.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'JobApplicants.is_deleted' => false,
            'Jobs.is_deleted' => false
        ];
        
        $contain = [
            'Jobs' => [
                'fields' => ['id', 'name', 'status'],
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Job Applicants'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'job_id', 'name', 'email', 'mobile', 'job_type', 'experience', 'area_expertise', 'resume_path', 'created','qualification'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['JobApplicants.created' => 'DESC'],
            'sortWhitelist' => ['JobApplicants.id', 'JobApplicants.name', 'JobApplicants.email', 'JobApplicants.mobile', 'JobApplicants.job_type', 'JobApplicants.experience', 'JobApplicants.created', 'Jobs.name']
        ];
        
        try
        {
            $jobApplicants = $this->paginate($this->JobApplicants);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->JobApplicants->find()
                ->contain($contain)
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $jobs = $this->JobApplicants->Jobs->find('list')
            ->where(['Jobs.is_deleted' => false])
            ->order(['Jobs.lft' => 'ASC']);
        
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
        $this->set(compact('jobApplicants', 'jobs', 'imageRoot', 'imageDir'));
        $this->set('activeMenu', 'Admin.JobApplicants.index');
        $this->JobApplicants->updateAll(['view' => true],['JobApplicants.is_deleted' => false]);
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'JobApplicants');
        }
    }
    
    public function export()
    {
		$imageDir = static::IMAGE_DIR;
        list($conditions, $contain) = $this->_index();
        $jobApplicants = $this->JobApplicants->find()
            ->contain($contain)
            ->where($conditions)
            ->order(['JobApplicants.created' => 'DESC']);
        
        $this->set(compact('jobApplicants','imageDir'));
        
        $this->viewBuilder()->layout('default');
    }
    
    public function delete($id = null)
    {
        try
        {
            $this->request->allowMethod(['post', 'delete']);
        }
        catch(MethodNotAllowedException $e)
        {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        try
        {
            $jobApplicant = $this->JobApplicants->get($id, [
                'contain' => [
                    'Jobs' => [
                        'fields' => ['id', 'name', 'status'],
                    ]
                ],
                'conditions' => [
                    'JobApplicants.is_deleted' => false,
                    'Jobs.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid applicant selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $jobApplicant->is_deleted = NULL;
        if($this->JobApplicants->save($jobApplicant))
        {
			$this->Flash->success(__('The applicant has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The applicant could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
