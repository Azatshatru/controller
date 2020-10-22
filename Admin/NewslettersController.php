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
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class NewslettersController extends AppController
{
    const FILE_PATH = 'newsletters'.DS;
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
            ['name', 'Newsletters.name', 'LIKE', ''],
			['newsletter_category_id', 'NewsletterCategories.lft', 'TREELISTCURRENT', '', '', $this->Newsletters->NewsletterCategories],
            ['from_date_from', 'Newsletters.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'Newsletters.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'Newsletters.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'Newsletters.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'Newsletters.status', 'EQUALS', ''],
            ['created_from', 'Newsletters.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Newsletters.created', 'lessThanOrEqual', 'DATE'],
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Newsletters.is_deleted' => false
        ];
        
        $contain = [
            'NewsletterCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Newsletters'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'file_name', 'status', 'lft', 'created','newsletter_category_id'],
			'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['Newsletters.lft' => 'ASC'],
            'sortWhitelist' => ['Newsletters.id', 'Newsletters.name', 'Newsletters.from_date', 'Newsletters.to_date', 'Newsletters.status', 'Newsletters.lft', 'Newsletters.created']
        ];
        
        try
        {
            $newsletters = $this->paginate($this->Newsletters); 
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Newsletters->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->Newsletters->find('list', [
            'valueField' => 'newsletter_category_id'
        ])->where($conditions)
            ->order(['Newsletters.newsletter_category_id' => 'ASC', 'Newsletters.lft' => 'ASC']);
        
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
		$newsletterCategories = $this->Newsletters->NewsletterCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['NewsletterCategories.is_deleted' => false])
            ->andWhere(['NewsletterCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
        foreach($newsletterCategories as $key => $value)
        {
            $categoryPath[$key] = $this->Newsletters->NewsletterCategories->find('path', ['for' => $key])
                ->select(['id', 'name']);
        }
        $this->set(compact('newsletters', 'treeList', 'imageRoot', 'imageDir','newsletterCategories','categoryPath'));
        $this->set('activeMenu', 'Admin.Newsletters.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Newsletters');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Newsletter'));
        
        $newsletter = $this->Newsletters->newEntity();
        if($this->request->is('post'))
        {
            $newsletter2 = $this->request->withData('is_deleted', 0);
			
            $newsletter = $this->Newsletters->patchEntity($newsletter, $newsletter2->getData());
			
            if(!$newsletter->errors())
            {
                $errors = [];
                if($this->request->getData('file_download.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_EXTENSION));
                    $newsletter->file_name = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$newsletter->file_name);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $newsletter->file_name = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$newsletter->file_name);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('file_download.tmp_name'), static::IMAGE_ROOT.$newsletter->file_name);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload newsletterable file. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Newsletters->save($newsletter))
                    {
						if($this->request->getData('top_on_list'))
                        {
                            $this->Newsletters->moveUp($newsletter, true);
                        }
                        
                        $this->Flash->success(__('The newsletter has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The newsletter could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The newsletter could not be added. Please see warning(s) below.'));
            }
        }
		$this->loadModel('NewsletterCategories');
		$newsletterCategories = $this->NewsletterCategories->find('list')
		                         ->where(['is_deleted'=>false,'status'=>true]);
        $this->set(compact('newsletter','newsletterCategories'));
        $this->set('activeMenu', 'Admin.Newsletters.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Newsletter'));
        
        try
        {
            $newsletter = $this->Newsletters->get($id, [
                'conditions' => [
                    'Newsletters.is_deleted' => false
                ]
            ]);
            
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($newsletter->{$formattableField})
                {
                    $fieldDate = new Time($newsletter->{$formattableField});
                    $newsletter->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid newsletter selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $newsletter2 = $this->request->withData('is_deleted', $newsletter->is_deleted);
            $newsletter = $this->Newsletters->patchEntity($newsletter, $newsletter2->getData());
            if(!$newsletter->errors())
            {
                $errors = [];
                if($this->request->getData('file_download.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_EXTENSION));
                    $newsletter->file_name = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$newsletter->file_name);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $newsletter->file_name = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$newsletter->file_name);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('file_download.tmp_name'), static::IMAGE_ROOT.$newsletter->file_name);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload newsletterable file. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Newsletters->save($newsletter))
                    {
						$this->Flash->success(__('The newsletter has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The newsletter could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The newsletter could not be updated. Please see warning(s) below.'));
            }
        }
        $this->loadModel('NewsletterCategories');
		$newsletterCategory = $this->NewsletterCategories->find('list')
		                         ->where(['is_deleted'=>false,'status'=>true]);
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
        $this->set(compact('newsletter', 'imageRoot', 'imageDir','newsletterCategory'));
        $this->set('activeMenu', 'Admin.Newsletters.index');
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
            $newsletter = $this->Newsletters->get($id, [
                'conditions' => [
                    'Newsletters.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid newsletter selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $newsletter->is_deleted = NULL;
        if($this->Newsletters->save($newsletter))
        {
			$this->Flash->success(__('The newsletter has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The newsletter could not be deleted. Please try again.'));
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
            $newsletter = $this->Newsletters->get($id, [
                'conditions' => [
                    'Newsletters.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid newsletter selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $newsletter2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $newsletter2 = $this->request->withData('status', 0);
        }
        
        $newsletter = $this->Newsletters->patchEntity($newsletter, $newsletter2->getData());
        if($this->Newsletters->save($newsletter))
        {
			$this->Flash->success(__('The newsletter has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The newsletter could not be {0}. Please try again.', $status));
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
            $newsletter = $this->Newsletters->get($id, [
                'conditions' => [
                    'Newsletters.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid newsletter selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->Newsletters->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Newsletters.lft <' => $newsletter->lft])
                ->andWhere(['Newsletters.newsletter_category_id IS' => $newsletter->newsletter_category_id])
                ->order(['Newsletters.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Newsletters->find()
                    ->where(['newsletter_category_id IS' => $newsletter->newsletter_category_id])
                    ->andWhere(['lft <' => $newsletter->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Newsletters->moveUp($newsletter, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->Newsletters->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Newsletters.lft >' => $newsletter->lft])
                ->andWhere(['Newsletters.newsletter_category_id IS' => $newsletter->newsletter_category_id])
                ->order(['Newsletters.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Newsletters->find()
                    ->where(['newsletter_category_id IS' => $newsletter->newsletter_category_id])
                    ->andWhere(['lft >' => $newsletter->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Newsletters->moveDown($newsletter, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The newsletter has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The newsletter could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
