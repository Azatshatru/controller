<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class BannersController extends AppController
{
    const FILE_PATH = 'banners'.DS;
    
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
            ['name', 'Banners.name', 'LIKE', ''],
            ['alt_tag', 'Banners.alt_tag', 'LIKE', ''],
            ['url', 'Banners.url', 'LIKE', ''],
            ['from_date_from', 'Banners.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'Banners.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'Banners.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'Banners.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'Banners.status', 'EQUALS', ''],
            ['created_from', 'Banners.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Banners.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Banners.is_deleted' => false
        ];
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Banners'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'banner_image', 'alt_tag', 'url', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['Banners.lft' => 'ASC'],
            'sortWhitelist' => ['Banners.id', 'Banners.name', 'Banners.alt_tag', 'Banners.url', 'Banners.from_date', 'Banners.to_date', 'Banners.status', 'Banners.lft', 'Banners.created']
        ];
        
        try
        {
            $banners = $this->paginate($this->Banners);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Banners->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->Banners->find('list', [
            'valueField' => 'banner_category_id'
        ])->where($conditions)
            ->order(['Banners.banner_category_id' => 'ASC', 'Banners.lft' => 'ASC']);
        
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('banners', 'treeList', 'imageRoot'));
        $this->set('activeMenu', 'Admin.Banners.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Banners');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Banner'));
        
        $banner = $this->Banners->newEntity();
        if($this->request->is('post'))
        {
            $banner2 = $this->request->withData('is_deleted', 0);
            $banner = $this->Banners->patchEntity($banner, $banner2->getData());
			
            if(!$banner->errors())
            {
                $errors = [];
                if($this->request->getData('banner_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('banner_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('banner_file.name'), PATHINFO_EXTENSION));
                    $banner->banner_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$banner->banner_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $banner->banner_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$banner->banner_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('banner_file.tmp_name'), static::IMAGE_ROOT.$banner->banner_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload banner image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Banners->save($banner))
                    {
						
                        if($this->request->getData('top_on_list'))
                        {
                            $this->Banners->moveUp($banner, true);
                        }
                        
                        $this->Flash->success(__('The banner has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The banner could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The banner could not be added. Please see warning(s) below.'));
            }
        }
        
     
        $this->set(compact('banner'));
        $this->set('activeMenu', 'Admin.Banners.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Banner'));
        
        try
        {
            $banner = $this->Banners->get($id, [
               'conditions' => [
                    'Banners.is_deleted' => false
                ]
            ]);
            $bannerDb = clone $banner;
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($banner->{$formattableField})
                {
                    $fieldDate = new Time($banner->{$formattableField});
                    $banner->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid banner selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $banner2 = $this->request->withData('is_deleted', $banner->is_deleted);
            $banner = $this->Banners->patchEntity($banner, $banner2->getData());
            if(!$banner->errors())
            {
                $errors = [];
				
                if($this->request->getData('banner_file.tmp_name')!='')
                {    
                    $fileName = strtolower(pathinfo($this->request->getData('banner_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('banner_file.name'), PATHINFO_EXTENSION));
                    $banner->banner_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$banner->banner_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                   
                    $i = 1;
                    while($file->exists())
                    {
                        $banner->banner_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$banner->banner_image);
                    }
                     
                    $success = move_uploaded_file($this->request->getData('banner_file.tmp_name'), static::IMAGE_ROOT.$banner->banner_image);
                    if(!$success)
                    {   
                        $errors[] = __('Unable to upload banner image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {  
                    if($this->Banners->save($banner))
                    {
						
                        $this->Flash->success(__('The banner has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The banner could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The banner could not be updated. Please see warning(s) below.'));
            }
        }
       
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('banner', 'imageRoot'));
        $this->set('activeMenu', 'Admin.Banners.index');
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
            $banner = $this->Banners->get($id, [
                'conditions' => [
                    'Banners.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid banner selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $banner->is_deleted = NULL;
        if($this->Banners->save($banner))
        {
			
            $this->Flash->success(__('The banner has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The banner could not be deleted. Please try again.'));
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
            $banner = $this->Banners->get($id, [
                'conditions' => [
                    'Banners.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid banner selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $banner2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $banner2 = $this->request->withData('status', 0);
        }
        
        $banner = $this->Banners->patchEntity($banner, $banner2->getData());
        if($this->Banners->save($banner))
        {
			
            $this->Flash->success(__('The banner has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The banner could not be {0}. Please try again.', $status));
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
            $banner = $this->Banners->get($id, [
                'conditions' => [
                    'Banners.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid banner selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->Banners->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Banners.lft <' => $banner->lft])
                ->andWhere(['Banners.banner_category_id IS' => $banner->banner_category_id])
                ->order(['Banners.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Banners->find()
                    ->where(['banner_category_id IS' => $banner->banner_category_id])
                    ->andWhere(['lft <' => $banner->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Banners->moveUp($banner, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->Banners->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Banners.lft >' => $banner->lft])
                ->andWhere(['Banners.banner_category_id IS' => $banner->banner_category_id])
                ->order(['Banners.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Banners->find()
                    ->where(['banner_category_id IS' => $banner->banner_category_id])
                    ->andWhere(['lft >' => $banner->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Banners->moveDown($banner, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The banner has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The banner could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
