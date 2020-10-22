<?php
/**
 * @author: Manoj Tanwar
 * @date: April 23, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class JobsController extends AppController
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
            ['name', 'Jobs.name', 'LIKE', ''],
            ['from_date_from', 'Jobs.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'Jobs.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'Jobs.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'Jobs.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'Jobs.status', 'EQUALS', ''],
            ['created_from', 'Jobs.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Jobs.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Jobs.is_deleted' => false
        ];
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Jobs'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['Jobs.lft' => 'ASC'],
            'sortWhitelist' => ['Jobs.id', 'Jobs.name', 'Jobs.from_date', 'Jobs.to_date', 'Jobs.status', 'Jobs.lft', 'Jobs.created']
        ];
        
        try
        {
            $jobs = $this->paginate($this->Jobs);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Jobs->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->Jobs->find('list', [
            'valueField' => 'parent_id'
        ])->where($conditions)
            ->order(['parent_id' => 'ASC', 'lft' => 'ASC']);
        
        $this->set(compact('jobs', 'treeList'));
        $this->set('activeMenu', 'Admin.Jobs.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Jobs');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Job'));
        
        $job = $this->Jobs->newEntity();
        if($this->request->is('post'))
        {
            $job2 = $this->request->withData('is_deleted', 0);
            $job = $this->Jobs->patchEntity($job, $job2->getData());
            if($this->Jobs->save($job))
            {
				
                if($this->request->getData('top_on_list'))
                {
                    $this->Jobs->moveUp($job, true);
                }
                
                $this->Flash->success(__('The job has been added successfully.'));
                return $this->redirect(['action' => 'index']);
            }
            
            $this->Flash->error(__('The job could not be added. Please see warning(s) below.'));
        }
        
        $this->set(compact('job'));
        $this->set('activeMenu', 'Admin.Jobs.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Job'));
        
        try
        {
            $job = $this->Jobs->get($id, [
                'conditions' => [
                    'Jobs.is_deleted' => false
                ]
            ]);
            
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($job->{$formattableField})
                {
                    $fieldDate = new Time($job->{$formattableField});
                    $job->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid job selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $job2 = $this->request->withData('is_deleted', $job->is_deleted);
            $job = $this->Jobs->patchEntity($job, $job2->getData()); 
            if($this->Jobs->save($job))
            {
				
                $this->Flash->success(__('The job has been updated successfully.'));
                return $this->redirect($this->_redirectUrl());
            }
            
            $this->Flash->error(__('The job could not be updated. Please see warning(s) below.'));
        }
        
         $this->set(compact('job'));
        $this->set('activeMenu', 'Admin.Jobs.index');
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
            $job = $this->Jobs->get($id, [
                'conditions' => [
                    'Jobs.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid job selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $job->is_deleted = NULL;
        if($this->Jobs->save($job))
        {
			
            $this->Flash->success(__('The job has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The job could not be deleted. Please try again.'));
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
            $job = $this->Jobs->get($id, [
                'conditions' => [
                    'Jobs.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid job selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $job2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $job2 = $this->request->withData('status', 0);
        }
        
        $job = $this->Jobs->patchEntity($job, $job2->getData());
        if($this->Jobs->save($job))
        {
			
            $this->Flash->success(__('The job has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The job could not be {0}. Please try again.', $status));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    public function move($direction = 'down', $id = null)
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
            $job = $this->Jobs->get($id, [
                'conditions' => [
                    'Jobs.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid job selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->Jobs->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['lft <' => $job->lft])
                ->andWhere(['parent_id IS' => $job->parent_id])
                ->order(['lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Jobs->find()
                    ->where(['parent_id IS' => $job->parent_id])
                    ->andWhere(['lft <' => $job->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Jobs->moveUp($job, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->Jobs->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['lft >' => $job->lft])
                ->andWhere(['parent_id IS' => $job->parent_id])
                ->order(['lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Jobs->find()
                    ->where(['parent_id IS' => $job->parent_id])
                    ->andWhere(['lft >' => $job->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Jobs->moveDown($job, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The job has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The job could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
