<?php
/**
 * @author: Manoj Tanwar
 * @date: Fab 04, 2019
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

class DownloadCategoriesController extends AppController
{
    const FILE_PATH = 'download_categories'.DS;
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
            ['name', 'DownloadCategories.name', 'LIKE', ''],
            ['slug', 'DownloadCategories.slug', 'LIKE', ''],
            ['parent_id', 'DownloadCategories.lft', 'TREELIST', '', '', $this->DownloadCategories],
            ['from_date_from', 'DownloadCategories.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'DownloadCategories.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'DownloadCategories.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'DownloadCategories.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'DownloadCategories.status', 'EQUALS', ''],
            ['created_from', 'DownloadCategories.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'DownloadCategories.created', 'lessThanOrEqual', 'DATE'],
            /* ['image_categories_institutes.institute_id', '', 'SUBQUERY', '', 'institutes', $this->DownloadCategories, 'DownloadCategories.id', 'DownloadCategoriesInstitutes.image_category_id', ''] */
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        /* if(!empty($this->navInstitutes)){
             $this->loadModel('DownloadCategoriesInstitutes');
             $DownloadCategoriesInstitutes = $this->DownloadCategoriesInstitutes->find()
                        ->select(['DownloadCategoriesInstitutes.image_category_id'])
                        ->where(['DownloadCategoriesInstitutes.institute_id IN' => $this->navInstitutes])
                        ->distinct()->toArray();
             
            $ids = Hash::extract($DownloadCategoriesInstitutes, '{n}.image_category_id');
            if(!empty($ids)){
                $navSchoolcond = ['DownloadCategories.id IN' => $ids];
            }else{
                 $navSchoolcond = ['DownloadCategories.id' => 0];
            }
        }else{
            $navSchoolcond = '';
        } */
        $conditions = [
            $filters,
            'DownloadCategories.is_deleted' => false//,$navSchoolcond
        ];
        
        $contain = [
            'ParentDownloadCategories' => [
                'fields' => ['id', 'name', 'status']
            ],
            /* 'Institutes' => [
                'fields' => ['DownloadCategoriesInstitutes.image_category_id', 'id', 'name', 'status'],
                'conditions' => ['Institutes.is_deleted' => false]
            ] */
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Download Categories'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'slug', 'parent_id', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['DownloadCategories.lft' => 'ASC'],
            'sortWhitelist' => ['DownloadCategories.id', 'DownloadCategories.name', 'DownloadCategories.slug', 'DownloadCategories.from_date', 'DownloadCategories.to_date', 'DownloadCategories.status', 'DownloadCategories.lft', 'DownloadCategories.created', 'ParentDownloadCategories.name']
        ];
        
        try
        {
            $downloadCategories = $this->paginate($this->DownloadCategories);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->DownloadCategories->find()
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
        
       /*  $institutes = $this->DownloadCategories->Institutes->find('list', [
            'valueField' => function($institutes) {
                return $institutes->get('name').(!$institutes->get('status')?' (x)':'');
            }
        ])->select(['id', 'name', 'status'])
            ->where(['Institutes.is_deleted' => false])
            ->order(['Institutes.name' => 'ASC']); */
       /*  if(!empty($this->navInstitutes))
        {
            $institutes->matching('Users', function($q){
                 return $q->where(['Users.id' => $this->userId]);
             });
        } */
        $parents = $this->DownloadCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['DownloadCategories.is_deleted' => false])
            ->andWhere(['DownloadCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
        
        $treeList = $this->DownloadCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['DownloadCategories.is_deleted' => false]);
        
        $treeList2 = $this->DownloadCategories->find('list', [
            'valueField' => 'parent_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['DownloadCategories.parent_id' => 'ASC', 'DownloadCategories.lft' => 'ASC']);
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('downloadCategories', 'parents', 'treeList', 'treeList2', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.DownloadCategories.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'DownloadCategories');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Download Category'));
        
        $downloadCategory = $this->DownloadCategories->newEntity();
        if($this->request->is('post'))
        {
            $downloadCategory2 = $this->request->withData('is_deleted', 0);
            $downloadCategory = $this->DownloadCategories->patchEntity($downloadCategory, $downloadCategory2->getData());
            if(!$downloadCategory->errors())
            {
               if($this->DownloadCategories->save($downloadCategory))
				{
					//$this->call_admin();
					$this->Flash->success(__('The Download category has been added successfully.'));
					return $this->redirect(['action' => 'index']);
				}
				else
				{
					$this->Flash->error(__('The Download category could not be added. Please see warning(s) below.'));
				}
           }
            else
            {
                $this->Flash->error(__('The Download category could not be added. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->DownloadCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['DownloadCategories.is_deleted' => false])
            ->andWhere(['DownloadCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
        
        /* $institutes = $this->DownloadCategories->Institutes->find('list', [
            'valueField' => function($institutes) {
                return $institutes->get('name').(!$institutes->get('status')?' (x)':'');
            }
        ])->select(['id', 'name', 'status'])
            ->where(['Institutes.is_deleted' => false])
            ->order(['Institutes.name' => 'ASC']);
         if(!empty($this->navInstitutes))
        {
            $institutes->matching('Users', function($q){
                 return $q->where(['Users.id' => $this->userId]);
             });
        } 
        if($this->request->getData('parent_id'))
        {
            $institutes
                ->matching('DownloadCategories', function($q) {
                    return $q->where(['DownloadCategories.id' => $this->request->getData('parent_id')]);
                });
        } */
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $this->set(compact('downloadCategory', 'parents', 'imgMaximumLevel'));
        $this->set('activeMenu', 'Admin.DownloadCategories.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Download Category'));
        
        try
        {
            $downloadCategory = $this->DownloadCategories->get($id, [
                /* 'contain' => [
                    'Institutes' => [
                        'fields' => ['DownloadCategoriesInstitutes.image_category_id', 'id', 'name', 'status'],
                        'conditions' => ['Institutes.is_deleted' => false]
                    ]
                ], */
                'conditions' => [
                    'DownloadCategories.is_deleted' => false
                ]
            ]);
            
            $downloadCategoryDb = clone $downloadCategory;
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($downloadCategory->{$formattableField})
                {
                    $fieldDate = new Time($downloadCategoryDb->{$formattableField});
                    $downloadCategoryDb->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Download category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $downloadCategory2 = $this->request->withData('is_deleted', $downloadCategory->is_deleted);
            $downloadCategory = $this->DownloadCategories->patchEntity($downloadCategory, $downloadCategory2->getData());
            if(!$downloadCategory->errors())
            {
                if($this->DownloadCategories->save($downloadCategory))
				{
					//$this->call_admin();
					$this->Flash->success(__('The Download category has been updated successfully.'));
					return $this->redirect($this->_redirectUrl());
				}
				else
				{
					$this->Flash->error(__('The Download category could not be updated. Please see warning(s) below.'));
				}
            }
            else
            {
                $this->Flash->error(__('The Download category could not be updated. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->DownloadCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['DownloadCategories.is_deleted' => false])
            ->andWhere(['DownloadCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1])
            ->andWhere(function($exp, $q) use($downloadCategoryDb) {
                return $exp->not([
                    $q->newExpr()->between('lft', $downloadCategoryDb->lft, $downloadCategoryDb->rght)
                ]);
            });
        
        /* $institutes = $this->DownloadCategories->Institutes->find('list', [
            'valueField' => function($institutes) {
                return $institutes->get('name').(!$institutes->get('status')?' (x)':'');
            }
        ])->select(['id', 'name', 'status'])
            ->where(['Institutes.is_deleted' => false])
            ->order(['Institutes.name' => 'ASC']);
         if(!empty($this->navInstitutes))
        {
            $institutes->matching('Users', function($q){
                 return $q->where(['Users.id' => $this->userId]);
             });
        }
        if($imageCategory->parent_id)
        {
            $institutes
                ->matching('DownloadCategories', function($q) use($imageCategory) {
                    return $q->where(['DownloadCategories.id' => $imageCategory->parent_id]);
                });
        } */
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('downloadCategory','parents', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.DownloadCategories.index');
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
            $downloadCategory = $this->DownloadCategories->get($id, [
                /* 'contain' => [
                    'Institutes' => [
                        'fields' => ['DownloadCategoriesInstitutes.image_category_id', 'id', 'name', 'status'],
                        'conditions' => ['Institutes.is_deleted' => false]
                    ]
                ], */
                'conditions' => [
                    'DownloadCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid Download category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $downloadCategory->is_deleted = NULL;
        if($this->DownloadCategories->save($downloadCategory))
        {
			//$this->call_admin();
            $this->DownloadCategories->query()
                ->update()
                ->set(['is_deleted' => NULL])
                ->where(function($exp, $q) use($downloadCategory) {
                    return $exp->between('lft', $downloadCategory->lft+1, $downloadCategory->rght-1);
                })
                ->andWhere(['DownloadCategories.is_deleted' => false])
                ->execute();
            
            $this->Flash->success(__('The Download category and all its child has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The Download category could not be deleted. Please try again.'));
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
            $downloadCategory = $this->DownloadCategories->get($id, [
                /* 'contain' => [
                    'Institutes' => [
                        'fields' => ['DownloadCategoriesInstitutes.image_category_id', 'id', 'name', 'status'],
                        'conditions' => ['Institutes.is_deleted' => false]
                    ]
                ], */
                'conditions' => [
                    'DownloadCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid download category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $downloadCategory2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $downloadCategory2 = $this->request->withData('status', 0);
        }
        
        $downloadCategory = $this->DownloadCategories->patchEntity($downloadCategory, $downloadCategory2->getData());
        if($this->DownloadCategories->save($downloadCategory))
        {
			//$this->call_admin();
            $this->Flash->success(__('The download category has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The download category could not be {0}. Please try again.', $status));
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
            $downloadCategory = $this->DownloadCategories->get($id, [
               /*  'contain' => [
                    'Institutes' => [
                        'fields' => ['DownloadCategoriesInstitutes.image_category_id', 'id', 'name', 'status'],
                        'conditions' => ['Institutes.is_deleted' => false]
                    ]
                ], */
                'conditions' => [
                    'DownloadCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid image category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->DownloadCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['DownloadCategories.lft <' => $downloadCategory->lft])
                ->andWhere(['DownloadCategories.parent_id IS' => $downloadCategory->parent_id])
                ->order(['DownloadCategories.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->DownloadCategories->find()
                    ->where(['parent_id IS' => $downloadCategory->parent_id])
                    ->andWhere(['lft <' => $downloadCategory->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->DownloadCategories->moveUp($downloadCategory, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->DownloadCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['DownloadCategories.lft >' => $downloadCategory->lft])
                ->andWhere(['DownloadCategories.parent_id IS' => $downloadCategory->parent_id])
                ->order(['DownloadCategories.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->DownloadCategories->find()
                    ->where(['parent_id IS' => $downloadCategory->parent_id])
                    ->andWhere(['lft >' => $downloadCategory->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->DownloadCategories->moveDown($downloadCategory, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The download category has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The download category could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
