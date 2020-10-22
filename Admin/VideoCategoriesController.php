<?php
/**
 * @author: Manoj Tanwar
 * @date: Apr 25, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class VideoCategoriesController extends AppController
{
    const FILE_PATH = 'video_categories'.DS;
    const VIDEO_MAXIMUM_LEVEL = 1;
    
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
            ['name', 'VideoCategories.name', 'LIKE', ''],
            ['slug', 'VideoCategories.slug', 'LIKE', ''],
            ['parent_id', 'VideoCategories.lft', 'TREELIST', '', '', $this->VideoCategories],
            ['from_date_from', 'VideoCategories.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'VideoCategories.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'VideoCategories.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'VideoCategories.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'VideoCategories.status', 'EQUALS', ''],
            ['created_from', 'VideoCategories.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'VideoCategories.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'VideoCategories.is_deleted' => false
        ];
        
        $contain = [
            'ParentVideoCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Video Categories'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'slug', 'parent_id', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['VideoCategories.lft' => 'ASC'],
            'sortWhitelist' => ['VideoCategories.id', 'VideoCategories.name', 'VideoCategories.slug', 'VideoCategories.from_date', 'VideoCategories.to_date', 'VideoCategories.status', 'VideoCategories.lft', 'VideoCategories.created', 'ParentVideoCategories.name']
        ];
        
        try
        {
            $videoCategories = $this->paginate($this->VideoCategories);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->VideoCategories->find()
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
        
        $parents = $this->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL - 1]);
        
        $treeList = $this->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false]);
        
        $treeList2 = $this->VideoCategories->find('list', [
            'valueField' => 'parent_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['VideoCategories.parent_id' => 'ASC', 'VideoCategories.lft' => 'ASC']);
        
        $videoMaximumLevel = static::VIDEO_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('videoCategories', 'parents', 'treeList', 'treeList2', 'videoMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.VideoCategories.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'VideoCategories');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Video Category'));
        
        $videoCategory = $this->VideoCategories->newEntity();
        if($this->request->is('post'))
        {
            $videoCategory2 = $this->request->withData('is_deleted', 0);
            $videoCategory = $this->VideoCategories->patchEntity($videoCategory, $videoCategory2->getData());
            if(!$videoCategory->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $videoCategory->category_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$videoCategory->category_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $videoCategory->category_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$videoCategory->category_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$videoCategory->category_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload category image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->VideoCategories->save($videoCategory))
                    {
						$this->Flash->success(__('The video category has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The video category could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The video category could not be added. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL - 1]);
        
        $videoMaximumLevel = static::VIDEO_MAXIMUM_LEVEL;
        $this->set(compact('videoCategory', 'parents', 'videoMaximumLevel'));
        $this->set('activeMenu', 'Admin.VideoCategories.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Video Category'));
        
        try
        {
            $videoCategory = $this->VideoCategories->get($id, [
                'conditions' => [
                    'VideoCategories.is_deleted' => false
                ]
            ]);
            
            $videoCategoryDb = clone $videoCategory;
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($videoCategory->{$formattableField})
                {
                    $fieldDate = new Time($videoCategory->{$formattableField});
                    $videoCategory->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid video category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $videoCategory2 = $this->request->withData('is_deleted', $videoCategory->is_deleted);
            $videoCategory = $this->VideoCategories->patchEntity($videoCategory, $videoCategory2->getData());
            if(!$videoCategory->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $videoCategory->category_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$videoCategory->category_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $videoCategory->category_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$videoCategory->category_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$videoCategory->category_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload category image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->VideoCategories->save($videoCategory))
                    {
						
                        $this->Flash->success(__('The video category has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The video category could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The video category could not be updated. Please see warning(s) below.'));
            }
        }
        
        $parents = $this->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL - 1])
            ->andWhere(function($exp, $q) use($videoCategoryDb) {
                return $exp->not([
                    $q->newExpr()->between('lft', $videoCategoryDb->lft, $videoCategoryDb->rght)
                ]);
            });
        
        $videoMaximumLevel = static::VIDEO_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('videoCategory', 'parents', 'videoMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.VideoCategories.index');
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
            $videoCategory = $this->VideoCategories->get($id, [
                'conditions' => [
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid video category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $videoCategory->is_deleted = NULL;
        if($this->VideoCategories->save($videoCategory))
        {
			
            $this->VideoCategories->query()
                ->update()
                ->set(['is_deleted' => NULL])
                ->where(function($exp, $q) use($videoCategory) {
                    return $exp->between('lft', $videoCategory->lft+1, $videoCategory->rght-1);
                })
                ->andWhere(['VideoCategories.is_deleted' => false])
                ->execute();
            
            $this->Flash->success(__('The video category and all its child has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The video category could not be deleted. Please try again.'));
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
            $videoCategory = $this->VideoCategories->get($id, [
                'conditions' => [
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid video category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $videoCategory2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $videoCategory2 = $this->request->withData('status', 0);
        }
        
        $videoCategory = $this->VideoCategories->patchEntity($videoCategory, $videoCategory2->getData());
        if($this->VideoCategories->save($videoCategory))
        {
			
            $this->Flash->success(__('The video category has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The video category could not be {0}. Please try again.', $status));
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
            $videoCategory = $this->VideoCategories->get($id, [
                'conditions' => [
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid video category selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->VideoCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['VideoCategories.lft <' => $videoCategory->lft])
                ->andWhere(['VideoCategories.parent_id IS' => $videoCategory->parent_id])
                ->order(['VideoCategories.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->VideoCategories->find()
                    ->where(['parent_id IS' => $videoCategory->parent_id])
                    ->andWhere(['lft <' => $videoCategory->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->VideoCategories->moveUp($videoCategory, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->VideoCategories->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['VideoCategories.lft >' => $videoCategory->lft])
                ->andWhere(['VideoCategories.parent_id IS' => $videoCategory->parent_id])
                ->order(['VideoCategories.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->VideoCategories->find()
                    ->where(['parent_id IS' => $videoCategory->parent_id])
                    ->andWhere(['lft >' => $videoCategory->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->VideoCategories->moveDown($videoCategory, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The video category has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The video category could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
