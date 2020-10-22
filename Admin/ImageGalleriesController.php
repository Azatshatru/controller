<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\Utility\Hash;

class ImageGalleriesController extends AppController
{
    const FILE_PATH = 'image_galleries'.DS;
    const IMG_MAXIMUM_LEVEL = 2;
    
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
            ['name', 'ImageGalleries.name', 'LIKE', ''],
            ['image_category_id', 'ImageCategories.lft', 'TREELISTCURRENT', '', '', $this->ImageGalleries->ImageCategories],
            ['status', 'ImageGalleries.status', 'EQUALS', ''],
            ['created_from', 'ImageGalleries.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'ImageGalleries.created', 'lessThanOrEqual', 'DATE'],
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'ImageGalleries.is_deleted' => false,
            'ImageCategories.is_deleted' => false
        ];
        
        $contain = [
            'ImageCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Gallery Images'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'image_category_id', 'photo_image', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['ImageGalleries.lft' => 'ASC'],
            'sortWhitelist' => ['ImageGalleries.id', 'ImageGalleries.name', 'ImageGalleries.status', 'ImageGalleries.lft', 'ImageGalleries.created', 'ImageCategories.name']
        ];
        
        try
        {
            $imageGalleries = $this->paginate($this->ImageGalleries);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->ImageGalleries->find()
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
        
        
        $treeList = $this->ImageGalleries->find('list', [
            'valueField' => 'image_category_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['ImageGalleries.image_category_id' => 'ASC', 'ImageGalleries.lft' => 'ASC']);
        
        $imageCategories = $this->ImageGalleries->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
        foreach($imageCategories as $key => $value)
        {
            $categoryPath[$key] = $this->ImageGalleries->ImageCategories->find('path', ['for' => $key])
                ->select(['id', 'name']);
        }
        
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('imageGalleries', 'imageCategories', 'treeList', 'categoryPath', 'imageRoot'));
        $this->set('activeMenu', 'Admin.ImageGalleries.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'ImageGalleries');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Gallery Image'));
        
        $imageGallery = $this->ImageGalleries->newEntity();
        if($this->request->is('post'))
        {
            $imageGallery2 = $this->request->withData('is_deleted', 0);
            $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $imageGallery2->getData());
            if(!$imageGallery->errors())
            {
                $errors = [];
                if($this->request->getData('photo_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('photo_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('photo_file.name'), PATHINFO_EXTENSION));
                    $imageGallery->photo_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $imageGallery->photo_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('photo_file.tmp_name'), static::IMAGE_ROOT.$imageGallery->photo_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload gallery photo. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    $this->ImageGalleries->behaviors()->Tree->config('scope', ['image_category_id' => $this->request->getData('image_category_id')]);
                    if($this->ImageGalleries->save($imageGallery))
                    {
						
                        if($this->request->getData('top_on_list'))
                        {
                            $this->ImageGalleries->moveUp($imageGallery, true);
                        }
                        
                        $this->Flash->success(__('The gallery image has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The gallery image could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The gallery image could not be added. Please see warning(s) below.'));
            }
        }
        
        $imageCategories = $this->ImageGalleries->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
       
        $this->set(compact('imageGallery', 'imageCategories'));
        $this->set('activeMenu', 'Admin.ImageGalleries.index');
    }
    
    public function bulk()
    { 
        $this->set('page_title', __('Add Bulk Images In Gallery'));
        
        $imageGallery = $this->ImageGalleries->newEntity();
        if($this->request->is('post'))
        {  
			
            $this->request = $this->request->withData('is_deleted', 0);
            $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $this->request->getData(), [
                'validate' => 'bulk'
            ]);  
			
            if(!$imageGallery->errors())
            {
                $flag = false;
                $errors = []; 
                foreach($this->request->getData('photo_bulk') as $key => $photoFile)
                {
					
                    $imageGallery = $this->ImageGalleries->newEntity();
                    $this->request = $this->request
                        ->withData('photo_file', $photoFile)
                        ->withData('is_deleted', 0);
                    $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $this->request->getData());
                    
                    if(!$imageGallery->errors())
                    {
                        if($this->request->getData('photo_bulk.'.$key.'.tmp_name'))
                        {
                            $fileName = strtolower(pathinfo($this->request->getData('photo_bulk.'.$key.'.name'), PATHINFO_FILENAME));
                            $fileExtension = strtolower(pathinfo($this->request->getData('photo_bulk.'.$key.'.name'), PATHINFO_EXTENSION));
                            $imageGallery->photo_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                            
                            $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                            $directory = $file->folder();
                            $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                            
                            $i = 1;
                            while($file->exists())
                            {
                                $imageGallery->photo_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                                $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                            }
                            
                            $success = move_uploaded_file($this->request->getData('photo_bulk.'.$key.'.tmp_name'), static::IMAGE_ROOT.$imageGallery->photo_image);
                            if(!$success)
                            {
                                $errors[$key] = __('Unable to upload gallery photo. Please try again.');
                            }
                        }
                        
                        if(empty($errors[$key]))
                        {
                            $this->ImageGalleries->behaviors()->Tree->config('scope', ['image_category_id' => $this->request->getData('image_category_id')]);
                            if($this->ImageGalleries->save($imageGallery))
                            {
                                if($this->request->getData('top_on_list'))
                                {
                                    $this->ImageGalleries->moveUp($imageGallery, true);
                                }
                            }
                            else
                            {
                                $flag = true;
                            }
                        }
                        else
                        {
                            $flag = true;
                        }
                    }
                    else
                    {
                        $flag = true;
                    }
				
                }
                
                if(!$flag)
                {
                    $this->Flash->success(__('The gallery image has been added successfully.'));
                    return $this->redirect(['action' => 'index']);
                }
                else
                {   
                    $this->Flash->error(__('Few gallery image(s) could not be added.'));
                }
            }
            else
            {  
                $this->Flash->error(__('The gallery image could not be added. Please see warning(s) below.'));
            }
        }
        
        $imageCategories = $this->ImageGalleries->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
       
        $this->set(compact('imageGallery', 'imageCategories'));
        $this->set('activeMenu', 'Admin.ImageGalleries.index');
    }
    
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Gallery Image'));
        
        try
        {
            $imageGallery = $this->ImageGalleries->get($id, [
                'contain' => [
                    'ImageCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'ImageGalleries.is_deleted' => false,
                    'ImageCategories.is_deleted' => false
                ]
            ]);
            
            $imageGalleryDb = clone $imageGallery;
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery image selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $imageGallery2 = $this->request->withData('is_deleted', $imageGallery->is_deleted);
            $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $imageGallery2->getData());
            if(!$imageGallery->errors())
            {
                $errors = [];
                if($this->request->getData('photo_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('photo_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('photo_file.name'), PATHINFO_EXTENSION));
                    $imageGallery->photo_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $imageGallery->photo_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$imageGallery->photo_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('photo_file.tmp_name'), static::IMAGE_ROOT.$imageGallery->photo_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload gallery photo. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->request->getData('image_category_id') != $imageGalleryDb->image_category_id)
                    {
                        $this->ImageGalleries->behaviors()->Tree->config('scope', ['image_category_id' => $imageGalleryDb->image_category_id]);
                        $this->ImageGalleries->moveDown($imageGalleryDb, true);
                        
                        $imageCount = $this->ImageGalleries->find()
                            ->where(['image_category_id' => $this->request->getData('image_category_id')])
                            ->count();
                        
                        $imageGallery2 = $this->request
                            ->withData('lft', ($imageCount*2)+1)
                            ->withData('rght', ($imageCount*2)+2);
                        
                        $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $imageGallery2->getData(), [
                            'accessibleFields' => ['lft' => true, 'rght' => true]
                        ]);
                    }
                    
                    if($this->ImageGalleries->save($imageGallery))
                    {
						$this->Flash->success(__('The gallery image has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The gallery image could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The gallery image could not be updated. Please see warning(s) below.'));
            }
        }
        
        $imageCategories = $this->ImageGalleries->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
       
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('imageGallery', 'imageCategories', 'imageRoot'));
        $this->set('activeMenu', 'Admin.ImageGalleries.index');
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
            $imageGallery = $this->ImageGalleries->get($id, [
                'contain' => [
                    'ImageCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'ImageGalleries.is_deleted' => false,
                    'ImageCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery image selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $imageGallery->is_deleted = NULL;
        if($this->ImageGalleries->save($imageGallery))
        {
			
            $this->Flash->success(__('The gallery image has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The gallery image could not be deleted. Please try again.'));
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
            $imageGallery = $this->ImageGalleries->get($id, [
                'contain' => [
                    'ImageCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'ImageGalleries.is_deleted' => false,
                    'ImageCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery image selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $imageGallery2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $imageGallery2 = $this->request->withData('status', 0);
        }
        
        $imageGallery = $this->ImageGalleries->patchEntity($imageGallery, $imageGallery2->getData());
        if($this->ImageGalleries->save($imageGallery))
        {
			$this->Flash->success(__('The gallery image has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The gallery image could not be {0}. Please try again.', $status));
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
            $imageGallery = $this->ImageGalleries->get($id, [
                'contain' => [
                    'ImageCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'ImageGalleries.is_deleted' => false,
                    'ImageCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery image selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        $this->ImageGalleries->behaviors()->Tree->config('scope', ['image_category_id' => $imageGallery->image_category_id]);
        if($direction == 'up')
        {
            $respectRecord = $this->ImageGalleries->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['ImageGalleries.lft <' => $imageGallery->lft])
                ->andWhere(['ImageGalleries.image_category_id IS' => $imageGallery->image_category_id])
                ->order(['ImageGalleries.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->ImageGalleries->find()
                    ->where(['image_category_id IS' => $imageGallery->image_category_id])
                    ->andWhere(['lft <' => $imageGallery->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->ImageGalleries->moveUp($imageGallery, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->ImageGalleries->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['ImageGalleries.lft >' => $imageGallery->lft])
                ->andWhere(['ImageGalleries.image_category_id IS' => $imageGallery->image_category_id])
                ->order(['ImageGalleries.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->ImageGalleries->find()
                    ->where(['image_category_id IS' => $imageGallery->image_category_id])
                    ->andWhere(['lft >' => $imageGallery->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->ImageGalleries->moveDown($imageGallery, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The gallery image has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The gallery image could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    
}
