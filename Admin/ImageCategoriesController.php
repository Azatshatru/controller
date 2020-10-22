<?php
/**
 * @author: Manoj Tanwar
 * @date: April 22, 2019
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

class ImageCategoriesController extends AppController
{
    const FILE_PATH = 'image_categories'.DS;
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
            ['name', 'ImageCategories.name', 'LIKE', ''],
            ['slug', 'ImageCategories.slug', 'LIKE', ''],
            ['parent_id', 'ImageCategories.lft', 'TREELIST', '', '', $this->ImageCategories],
            ['from_date_from', 'ImageCategories.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'ImageCategories.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'ImageCategories.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'ImageCategories.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'ImageCategories.status', 'EQUALS', ''],
            ['created_from', 'ImageCategories.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'ImageCategories.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
       
        $conditions = [
            $filters,
            'ImageCategories.is_deleted' => false
        ];
        
        $contain = [
            'ParentImageCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Image Categories'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'slug', 'parent_id', 'category_image', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['ImageCategories.lft' => 'ASC'],
            'sortWhitelist' => ['ImageCategories.id', 'ImageCategories.name', 'ImageCategories.slug', 'ImageCategories.from_date', 'ImageCategories.to_date', 'ImageCategories.status', 'ImageCategories.lft', 'ImageCategories.created', 'ParentImageCategories.name']
        ];
        
        try
        {
            $imageCategories = $this->paginate($this->ImageCategories);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->ImageCategories->find()
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
        
       
        $parents = $this->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
        
        $treeList = $this->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false]);
        
        $treeList2 = $this->ImageCategories->find('list', [
            'valueField' => 'parent_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['ImageCategories.parent_id' => 'ASC', 'ImageCategories.lft' => 'ASC']);
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('imageCategories', 'parents', 'treeList', 'treeList2', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.ImageCategories.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'ImageCategories');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Image Category'));
        
        $imageCategory = $this->ImageCategories->newEntity();
        if($this->request->is('post'))
        {
            $imageCategory2 = $this->request->withData('is_deleted', 0);
            $imageCategory = $this->ImageCategories->patchEntity($imageCategory, $imageCategory2->getData());
            if(!$imageCategory->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $imageCategory->category_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$imageCategory->category_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $imageCategory->category_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$imageCategory->category_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$imageCategory->category_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload category image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->ImageCategories->save($imageCategory))
                    {
						$this->Flash->success(__('The image category has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The image category could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The image category could not be added. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1]);
        
        
       
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $this->set(compact('imageCategory', 'parents', 'imgMaximumLevel'));
        $this->set('activeMenu', 'Admin.ImageCategories.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Image Category'));
        
        try
        {
            $imageCategory = $this->ImageCategories->get($id, [
                'conditions' => [
                    'ImageCategories.is_deleted' => false
                ]
            ]);
            
            $imageCategoryDb = clone $imageCategory;
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($imageCategory->{$formattableField})
                {
                    $fieldDate = new Time($imageCategory->{$formattableField});
                    $imageCategory->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid image category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $imageCategory2 = $this->request->withData('is_deleted', $imageCategory->is_deleted);
            $imageCategory = $this->ImageCategories->patchEntity($imageCategory, $imageCategory2->getData());
            if(!$imageCategory->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $imageCategory->category_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$imageCategory->category_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $imageCategory->category_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$imageCategory->category_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$imageCategory->category_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload category image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->ImageCategories->save($imageCategory))
                    {
						$this->Flash->success(__('The image category has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The image category could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The image category could not be updated. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL - 1])
            ->andWhere(function($exp, $q) use($imageCategoryDb) {
                return $exp->not([
                    $q->newExpr()->between('lft', $imageCategoryDb->lft, $imageCategoryDb->rght)
                ]);
            });
        
       
       
        
        $imgMaximumLevel = static::IMG_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('imageCategory', 'parents', 'imgMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.ImageCategories.index');
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
            $imageCategory = $this->ImageCategories->get($id, [
                'conditions' => [
                    'ImageCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid image category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $imageCategory->is_deleted = NULL;
        if($this->ImageCategories->save($imageCategory))
        {
			 $this->ImageCategories->query()
                ->update()
                ->set(['is_deleted' => NULL])
                ->where(function($exp, $q) use($imageCategory) {
                    return $exp->between('lft', $imageCategory->lft+1, $imageCategory->rght-1);
                })
                ->andWhere(['ImageCategories.is_deleted' => false])
                ->execute();
            
            $this->Flash->success(__('The image category and all its child has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The image category could not be deleted. Please try again.'));
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
            $imageCategory = $this->ImageCategories->get($id, [
               'conditions' => [
                    'ImageCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid image category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $imageCategory2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $imageCategory2 = $this->request->withData('status', 0);
        }
        
        $imageCategory = $this->ImageCategories->patchEntity($imageCategory, $imageCategory2->getData());
        if($this->ImageCategories->save($imageCategory))
        {
			$this->Flash->success(__('The image category has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The image category could not be {0}. Please try again.', $status));
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
            $imageCategory = $this->ImageCategories->get($id, [
                'conditions' => [
                    'ImageCategories.is_deleted' => false
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
            $respectRecord = $this->ImageCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['ImageCategories.lft <' => $imageCategory->lft])
                ->andWhere(['ImageCategories.parent_id IS' => $imageCategory->parent_id])
                ->order(['ImageCategories.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->ImageCategories->find()
                    ->where(['parent_id IS' => $imageCategory->parent_id])
                    ->andWhere(['lft <' => $imageCategory->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->ImageCategories->moveUp($imageCategory, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->ImageCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['ImageCategories.lft >' => $imageCategory->lft])
                ->andWhere(['ImageCategories.parent_id IS' => $imageCategory->parent_id])
                ->order(['ImageCategories.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->ImageCategories->find()
                    ->where(['parent_id IS' => $imageCategory->parent_id])
                    ->andWhere(['lft >' => $imageCategory->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->ImageCategories->moveDown($imageCategory, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The image category has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The image category could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
