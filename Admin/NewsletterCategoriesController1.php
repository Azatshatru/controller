<?php
/**
 * @author: Manoj Tanwar
 * @date: April 24, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\Utility\Hash;

class NewsletterCategoriesController extends AppController
{
    const FILE_PATH = 'newsletter_categories'.DS;
    const IMG_MAXIMUM_LEVEL = 3;
    
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
            ['name', 'NewsletterCategories.name', 'LIKE', ''],
            ['parent_id', 'NewsletterCategories.lft', 'TREELIST', '', '', $this->NewsletterCategories],
            ['from_date_from', 'NewsletterCategories.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'NewsletterCategories.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'NewsletterCategories.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'NewsletterCategories.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'NewsletterCategories.status', 'EQUALS', ''],
            ['created_from', 'NewsletterCategories.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'NewsletterCategories.created', 'lessThanOrEqual', 'DATE'],
            
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
       
        $conditions = [
            $filters,
            'NewsletterCategories.is_deleted' => false//,$navSchoolcond
        ];
        
        $contain = [
            'ParentNewsletterCategories' => [
                'fields' => ['id', 'name', 'status']
            ],
            
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Newsletter Categories'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'parent_id', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['NewsletterCategories.lft' => 'ASC'],
            'sortWhitelist' => ['NewsletterCategories.id', 'NewsletterCategories.name', 'NewsletterCategories.from_date', 'NewsletterCategories.to_date', 'NewsletterCategories.status', 'NewsletterCategories.lft', 'NewsletterCategories.created', 'ParentNewsletterCategories.name']
        ];
        
        try
        {
            $newsletterCategories = $this->paginate($this->NewsletterCategories);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->NewsletterCategories->find()
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
        
       
        $parents = $this->NewsletterCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['NewsletterCategories.is_deleted' => false])
            ->andWhere(['NewsletterCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
        
        $treeList = $this->NewsletterCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['NewsletterCategories.is_deleted' => false]);
        
        $treeList2 = $this->NewsletterCategories->find('list', [
            'valueField' => 'parent_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['NewsletterCategories.parent_id' => 'ASC', 'NewsletterCategories.lft' => 'ASC']);
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('newsletterCategories', 'parents', 'treeList', 'treeList2', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.NewsletterCategories.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'NewsletterCategories');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Newsletter Category'));
        
        $newsletterCategory = $this->NewsletterCategories->newEntity();
        if($this->request->is('post'))
        {
            $newsletterCategory2 = $this->request->withData('is_deleted', 0);
            $newsletterCategory = $this->NewsletterCategories->patchEntity($newsletterCategory, $newsletterCategory2->getData()); 
            if(!$newsletterCategory->errors())
            {  
		      
               if($this->NewsletterCategories->save($newsletterCategory))
				{
					$this->Flash->success(__('The Newsletter category has been added successfully.'));
					return $this->redirect(['action' => 'index']);
				}
				else
				{
					$this->Flash->error(__('The Newsletter category could not be added. Please see warning(s) below.'));
				}
           }
            else
            { 
                $this->Flash->error(__('The Newsletter category could not be added. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->NewsletterCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['NewsletterCategories.is_deleted' => false])
            ->andWhere(['NewsletterCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
       
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $this->set(compact('newsletterCategory', 'parents', 'imgMaximumLevel'));
        $this->set('activeMenu', 'Admin.NewsletterCategories.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Newsletter Category'));
        
        try
        {
            $newsletterCategory = $this->NewsletterCategories->get($id, [
				'conditions' => [
                    'NewsletterCategories.is_deleted' => false
                ]
            ]);
            
            $newsletterCategoryDb = clone $newsletterCategory;
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($newsletterCategory->{$formattableField})
                {
                    $fieldDate = new Time($newsletterCategoryDb->{$formattableField});
                    $newsletterCategoryDb->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Newsletter category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
			
            $newsletterCategory2 = $this->request->withData('is_deleted', $newsletterCategory->is_deleted);
            $newsletterCategory = $this->NewsletterCategories->patchEntity($newsletterCategory, $newsletterCategory2->getData());
            if(!$newsletterCategory->errors())
            {
				
                if($this->NewsletterCategories->save($newsletterCategory))
				{ 
					$this->Flash->success(__('The Newsletter category has been updated successfully.'));
					return $this->redirect($this->_redirectUrl());
				}
				else
				{
					$this->Flash->error(__('The Newsletter category could not be updated. Please see warning(s) below.'));
				}
            }
            else
            {
                $this->Flash->error(__('The Newsletter category could not be updated. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->NewsletterCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['NewsletterCategories.is_deleted' => false])
            ->andWhere(['NewsletterCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1])
            ->andWhere(function($exp, $q) use($newsletterCategoryDb) {
                return $exp->not([
                    $q->newExpr()->between('lft', $newsletterCategoryDb->lft, $newsletterCategoryDb->rght)
                ]);
            });
        
        
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('newsletterCategory','parents', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.NewsletterCategories.index');
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
            $newsletterCategory = $this->NewsletterCategories->get($id, [
                'conditions' => [
                    'NewsletterCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Newsletter category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $newsletterCategory->is_deleted = NULL;
        if($this->NewsletterCategories->save($newsletterCategory))
        {
			$this->NewsletterCategories->query()
                ->update()
                ->set(['is_deleted' => NULL])
                ->where(function($exp, $q) use($newsletterCategory) {
                    return $exp->between('lft', $newsletterCategory->lft+1, $newsletterCategory->rght-1);
                })
                ->andWhere(['NewsletterCategories.is_deleted' => false])
                ->execute();
            
            $this->Flash->success(__('The Newsletter category and all its child has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The Newsletter category could not be deleted. Please try again.'));
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
            $newsletterCategory = $this->NewsletterCategories->get($id, [
               'conditions' => [
                    'NewsletterCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Newsletter category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $newsletterCategory2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $newsletterCategory2 = $this->request->withData('status', 0);
        }
        
        $newsletterCategory = $this->NewsletterCategories->patchEntity($newsletterCategory, $newsletterCategory2->getData());
        if($this->NewsletterCategories->save($newsletterCategory))
        {
			$this->Flash->success(__('The Newsletter category has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The Newsletter category could not be {0}. Please try again.', $status));
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
            $newsletterCategory = $this->NewsletterCategories->get($id, [
               'conditions' => [
                    'NewsletterCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Newsletter category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->NewsletterCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['NewsletterCategories.lft <' => $newsletterCategory->lft])
                ->andWhere(['NewsletterCategories.parent_id IS' => $newsletterCategory->parent_id])
                ->order(['NewsletterCategories.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->NewsletterCategories->find()
                    ->where(['parent_id IS' => $newsletterCategory->parent_id])
                    ->andWhere(['lft <' => $newsletterCategory->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->NewsletterCategories->moveUp($newsletterCategory, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->NewsletterCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['NewsletterCategories.lft >' => $newsletterCategory->lft])
                ->andWhere(['NewsletterCategories.parent_id IS' => $newsletterCategory->parent_id])
                ->order(['NewsletterCategories.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->NewsletterCategories->find()
                    ->where(['parent_id IS' => $newsletterCategory->parent_id])
                    ->andWhere(['lft >' => $newsletterCategory->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->NewsletterCategories->moveDown($newsletterCategory, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The newsletter category has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The newsletter category could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
