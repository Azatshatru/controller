<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class AlumnusController extends AppController
{
	const FILE_PATH = 'alumani'.DS;
	
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
            ['student_name', 'Alumnus.student_name', 'LIKE', ''],
            ['father_name', 'Alumnus.father_name', 'LIKE', ''],
            ['email', 'Alumnus.email', 'EQUALS', ''],
            ['Mobile', 'Alumnus.Mobile', 'EQUALS', ''],
            ['status', 'Alumnus.status', 'EQUALS', ''],
            ['created_from', 'Alumnus.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Alumnus.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Alumnus.is_deleted' => false
        ];
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Alumnus'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'student_name', 'father_name', 'student_img', 'email', 'status', 'mobile', 'created','address', 'occupation','passout_yr','passout_class','msg'],
            'conditions' => $conditions,
            'order' => ['Alumnus.id' => 'DESC'],
            'sortWhitelist' => ['Alumnus.id', 'Alumnus.student_name', 'Alumnus.father_name', 'Alumnus.occupation', 'Alumnus.address', 'Alumnus.email', 'Alumnus.status', 'Alumnus.created']
        ];
        
        try
        {
            $alumnus = $this->paginate($this->Alumnus);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Alumnus->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $imageRoot = static::IMAGE_ROOT;
		
        $this->set(compact('alumnus', 'imageRoot'));
        $this->set('activeMenu', 'Admin.Alumnus.index');
        
		
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Alumnus');
        }
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
            $alumni = $this->Alumnus->get($id, [
                'conditions' => [
                    'Alumnus.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid alumni selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $alumni->is_deleted = NULL;
        if($this->Alumnus->save($alumni))
        {
			$this->last_update();
            $this->Flash->success(__('The alumni has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The alumni could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    public function statusChange($state = 'inactive', $id = null)
    {
        try
        {
            $this->request->allowMethod(['post']);
        }
        catch(MethodNotAllowedException $e)
        {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        try
        {
            $alumni = $this->Alumnus->get($id, [
                'conditions' => [
                    'Alumnus.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid alumni selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $alumni2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $alumni2 = $this->request->withData('status', 0);
        }
        
        $alumni = $this->Alumnus->patchEntity($alumni, $alumni2->getData());
        if($this->Alumnus->save($alumni))
        {
			$this->Flash->success(__('The alumni has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The alumni could not be {0}. Please try again.', $status));
        }
        return $this->redirect($this->_redirectUrl());
    }    
}
