<?php
/**
 * @author: Manoj Tanwar
 * @date: April 22, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class AnnouncementsController extends AppController
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
            ['name', 'Announcements.name', 'LIKE', ''],
            ['url', 'Announcements.url', 'LIKE', ''],
            ['from_date_from', 'Announcements.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'Announcements.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'Announcements.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'Announcements.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'Announcements.status', 'EQUALS', ''],
            ['created_from', 'Announcements.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Announcements.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Announcements.is_deleted' => false
        ];
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Announcements'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'url', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['Announcements.lft' => 'ASC'],
            'sortWhitelist' => ['Announcements.id', 'Announcements.name', 'Announcements.url', 'Announcements.from_date', 'Announcements.to_date', 'Announcements.status', 'Announcements.lft', 'Announcements.created']
        ];
        
        try
        {
            $announcements = $this->paginate($this->Announcements);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Announcements->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->Announcements->find('list', [
            'valueField' => 'announcement_category_id'
        ])->where($conditions)
            ->order(['announcement_category_id' => 'ASC', 'lft' => 'ASC']);
        
        $this->set(compact('announcements', 'treeList'));
        $this->set('activeMenu', 'Admin.Announcements.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Announcements');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Announcement'));
        
        $announcement = $this->Announcements->newEntity();
        if($this->request->is('post'))
        {
            $announcement2 = $this->request->withData('is_deleted', 0);
            $announcement = $this->Announcements->patchEntity($announcement, $announcement2->getData());
            if($this->Announcements->save($announcement))
            {
				
                if($this->request->getData('top_on_list'))
                {
                    $this->Announcements->moveUp($announcement, true);
                }
                
                $this->Flash->success(__('The announcement has been added successfully.'));
                return $this->redirect(['action' => 'index']);
            }
            
            $this->Flash->error(__('The announcement could not be added. Please see warning(s) below.'));
        }
        
        $this->set(compact('announcement'));
        $this->set('activeMenu', 'Admin.Announcements.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Announcement'));
        
        try
        {
            $announcement = $this->Announcements->get($id, [
                'conditions' => [
                    'Announcements.is_deleted' => false
                ]
            ]);
            
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($announcement->{$formattableField})
                {
                    $fieldDate = new Time($announcement->{$formattableField});
                    $announcement->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid announcement selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $announcement2 = $this->request->withData('is_deleted', $announcement->is_deleted);
            $announcement = $this->Announcements->patchEntity($announcement, $announcement2->getData());
            if($this->Announcements->save($announcement))
            {
				
                $this->Flash->success(__('The announcement has been updated successfully.'));
                return $this->redirect($this->_redirectUrl());
            }
            
            $this->Flash->error(__('The announcement could not be updated. Please see warning(s) below.'));
        }
        
        $this->set(compact('announcement'));
        $this->set('activeMenu', 'Admin.Announcements.index');
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
            $announcement = $this->Announcements->get($id, [
                'conditions' => [
                    'Announcements.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid announcement selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $announcement->is_deleted = NULL;
        if($this->Announcements->save($announcement))
        {
			
            $this->Flash->success(__('The announcement has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The announcement could not be deleted. Please try again.'));
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
            $announcement = $this->Announcements->get($id, [
                'conditions' => [
                    'Announcements.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid announcement selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $announcement2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $announcement2 = $this->request->withData('status', 0);
        }
        
        $announcement = $this->Announcements->patchEntity($announcement, $announcement2->getData());
        if($this->Announcements->save($announcement))
        {
			
            $this->Flash->success(__('The announcement has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The announcement could not be {0}. Please try again.', $status));
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
            $announcement = $this->Announcements->get($id, [
                'conditions' => [
                    'Announcements.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid announcement selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->Announcements->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['lft <' => $announcement->lft])
                ->andWhere(['announcement_category_id IS' => $announcement->announcement_category_id])
                ->order(['lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Announcements->find()
                    ->where(['announcement_category_id IS' => $announcement->announcement_category_id])
                    ->andWhere(['lft <' => $announcement->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Announcements->moveUp($announcement, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->Announcements->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['lft >' => $announcement->lft])
                ->andWhere(['announcement_category_id IS' => $announcement->announcement_category_id])
                ->order(['lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Announcements->find()
                    ->where(['announcement_category_id IS' => $announcement->announcement_category_id])
                    ->andWhere(['lft >' => $announcement->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Announcements->moveDown($announcement, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The announcement has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The announcement could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
