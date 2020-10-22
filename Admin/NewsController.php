<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\Utility\Hash;

class NewsController extends AppController
{
    const FILE_PATH = 'news'.DS;
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
            ['name', 'News.name', 'LIKE', ''],
            ['from_date_from', 'News.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'News.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'News.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'News.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'News.status', 'EQUALS', ''],
            ['created_from', 'News.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'News.created', 'lessThanOrEqual', 'DATE'],
         ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
       
        $conditions = [
            $filters,
            'News.is_deleted' => false
        ];
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List News'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'from_date', 'to_date', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['News.lft' => 'ASC'],
            'sortWhitelist' => ['News.id', 'News.name', 'News.from_date', 'News.to_date', 'News.status', 'News.lft', 'News.created']
        ];
        
        try
        {
            $news = $this->paginate($this->News);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->News->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->News->find('list', [
            'valueField' => 'news_category_id'
        ])->where($conditions)
            ->order(['News.news_category_id' => 'ASC', 'News.lft' => 'ASC']);
        
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('news', 'treeList', 'imageRoot'));
        $this->set('activeMenu', 'Admin.News.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'News');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New News'));
        
        $news = $this->News->newEntity();
        if($this->request->is('post'))
        {
         
            $news2 = $this->request->withData('is_deleted', 0);
            $news = $this->News->patchEntity($news, $news2->getData());
            if(!$news->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $news->news_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$news->news_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $news->news_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$news->news_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$news->news_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload news image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->News->save($news))
                    {
						if($this->request->getData('top_on_list'))
                        {
                            $this->News->moveUp($news, true);
                        }
                        
                        $this->Flash->success(__('The news has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The news could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The news could not be added. Please see warning(s) below.'));
            }
        }
        
        
        $imageCategories = $this->News->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
        $this->set(compact('news','imageCategories'));
        $this->set('activeMenu', 'Admin.News.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit News'));
        
        try
        {
            $news = $this->News->get($id, [
                'conditions' => [
                    'News.is_deleted' => false
                ]
            ]);
            
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($news->{$formattableField})
                {
                    $fieldDate = new Time($news->{$formattableField});
                    $news->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid news selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $news2 = $this->request->withData('is_deleted', $news->is_deleted);
            $news = $this->News->patchEntity($news, $news2->getData());
            if(!$news->errors())
            {
                $errors = [];
                if($this->request->getData('image_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image_file.name'), PATHINFO_EXTENSION));
                    $news->news_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$news->news_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $news->news_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$news->news_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image_file.tmp_name'), static::IMAGE_ROOT.$news->news_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload news image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->News->save($news))
                    {
						$this->Flash->success(__('The news has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The news could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The news could not be updated. Please see warning(s) below.'));
            }
        }
       
        $imageCategories = $this->News->ImageCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['ImageCategories.is_deleted' => false])
            ->andWhere(['ImageCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('news', 'imageRoot','imageCategories','imagePath'));
        $this->set('activeMenu', 'Admin.News.index');
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
            $news = $this->News->get($id, [
                'conditions' => [
                    'News.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid news selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $news->is_deleted = NULL;
        if($this->News->save($news))
        {
			$this->Flash->success(__('The news has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The news could not be deleted. Please try again.'));
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
            $news = $this->News->get($id, [
                'conditions' => [
                    'News.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid news selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $news2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $news2 = $this->request->withData('status', 0);
        }
        
        $news = $this->News->patchEntity($news, $news2->getData());
        if($this->News->save($news))
        {
			$this->Flash->success(__('The news has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The news could not be {0}. Please try again.', $status));
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
            $news = $this->News->get($id, [
                'conditions' => [
                    'News.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid news selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->News->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['News.lft <' => $news->lft])
                ->andWhere(['News.news_category_id IS' => $news->news_category_id])
                ->order(['News.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->News->find()
                    ->where(['news_category_id IS' => $news->news_category_id])
                    ->andWhere(['lft <' => $news->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->News->moveUp($news, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->News->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['News.lft >' => $news->lft])
                ->andWhere(['News.news_category_id IS' => $news->news_category_id])
                ->order(['News.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->News->find()
                    ->where(['news_category_id IS' => $news->news_category_id])
                    ->andWhere(['lft >' => $news->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->News->moveDown($news, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The news has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The news could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
