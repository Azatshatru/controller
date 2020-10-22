<?php
/**
 * @author: Manoj Tanwar
 * @date: Fab 01, 2019
 * @version: 1.0.0
 */

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class DownloadsController extends AppController
{
    const FILE_PATH = 'downloads'.DS;
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
            ['name', 'Downloads.name', 'LIKE', ''],
			['download_category_id', 'DownloadCategories.lft', 'TREELISTCURRENT', '', '', $this->Downloads->DownloadCategories],
            ['from_date_from', 'Downloads.from_date', 'greaterThanOrEqual', 'DATE'],
            ['from_date_to', 'Downloads.from_date', 'lessThanOrEqual', 'DATE'],
            ['to_date_from', 'Downloads.to_date', 'greaterThanOrEqual', 'DATE'],
            ['to_date_to', 'Downloads.to_date', 'lessThanOrEqual', 'DATE'],
            ['status', 'Downloads.status', 'EQUALS', ''],
            ['created_from', 'Downloads.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Downloads.created', 'lessThanOrEqual', 'DATE'],
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Downloads.is_deleted' => false
        ];
        
        $contain = [
            'DownloadCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Downloads'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'file_name', 'from_date', 'to_date', 'status', 'lft', 'created','download_category_id'],
			'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['Downloads.lft' => 'ASC'],
            'sortWhitelist' => ['Downloads.id', 'Downloads.name', 'Downloads.from_date', 'Downloads.to_date', 'Downloads.status', 'Downloads.lft', 'Downloads.created']
        ];
        
        try
        {
            $downloads = $this->paginate($this->Downloads); 
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Downloads->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $treeList = $this->Downloads->find('list', [
            'valueField' => 'download_category_id'
        ])->where($conditions)
            ->order(['Downloads.download_category_id' => 'ASC', 'Downloads.lft' => 'ASC']);
        
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
		$downloadCategories = $this->Downloads->DownloadCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['DownloadCategories.is_deleted' => false])
            ->andWhere(['DownloadCategories.level <' => static::IMG_MAXIMUM_LEVEL]);
        
        foreach($downloadCategories as $key => $value)
        {
            $categoryPath[$key] = $this->Downloads->DownloadCategories->find('path', ['for' => $key])
                ->select(['id', 'name']);
        } 
        $this->set(compact('downloads', 'treeList', 'imageRoot', 'imageDir','downloadCategories','categoryPath'));
        $this->set('activeMenu', 'Admin.Downloads.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Downloads');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Download'));
        
        $download = $this->Downloads->newEntity();
        if($this->request->is('post'))
        {
            $download2 = $this->request->withData('is_deleted', 0);
            $download = $this->Downloads->patchEntity($download, $download2->getData());
            if(!$download->errors())
            {
                $errors = [];
                if($this->request->getData('file_download.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_EXTENSION));
                    $download->file_name = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$download->file_name);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $download->file_name = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$download->file_name);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('file_download.tmp_name'), static::IMAGE_ROOT.$download->file_name);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload downloadable file. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Downloads->save($download))
                    {
						
                        if($this->request->getData('top_on_list'))
                        {
                            $this->Downloads->moveUp($download, true);
                        }
                        
                        $this->Flash->success(__('The download has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The download could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The download could not be added. Please see warning(s) below.'));
            }
        }
		$this->loadModel('DownloadCategories');
		$downloadCategory = $this->DownloadCategories->find('list')
		                         ->where(['is_deleted'=>false,'status'=>true]);
        $this->set(compact('download','downloadCategory'));
        $this->set('activeMenu', 'Admin.Downloads.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Download'));
        
        try
        {
            $download = $this->Downloads->get($id, [
                'conditions' => [
                    'Downloads.is_deleted' => false
                ]
            ]);
            
            $formattableFields = ['from_date', 'to_date'];
            foreach($formattableFields as $formattableField)
            {
                if($download->{$formattableField})
                {
                    $fieldDate = new Time($download->{$formattableField});
                    $download->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid download selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $download2 = $this->request->withData('is_deleted', $download->is_deleted);
            $download = $this->Downloads->patchEntity($download, $download2->getData());
            if(!$download->errors())
            {
                $errors = [];
                if($this->request->getData('file_download.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('file_download.name'), PATHINFO_EXTENSION));
                    $download->file_name = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$download->file_name);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $download->file_name = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$download->file_name);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('file_download.tmp_name'), static::IMAGE_ROOT.$download->file_name);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload downloadable file. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    if($this->Downloads->save($download))
                    {
						
                        $this->Flash->success(__('The download has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The download could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The download could not be updated. Please see warning(s) below.'));
            }
        }
        $this->loadModel('DownloadCategories');
		$downloadCategory = $this->DownloadCategories->find('list')
		                         ->where(['is_deleted'=>false,'status'=>true]);
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
        $this->set(compact('download', 'imageRoot', 'imageDir','downloadCategory'));
        $this->set('activeMenu', 'Admin.Downloads.index');
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
            $download = $this->Downloads->get($id, [
                'conditions' => [
                    'Downloads.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid download selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $download->is_deleted = NULL;
        if($this->Downloads->save($download))
        {
			
            $this->Flash->success(__('The download has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The download could not be deleted. Please try again.'));
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
            $download = $this->Downloads->get($id, [
                'conditions' => [
                    'Downloads.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid download selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $download2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $download2 = $this->request->withData('status', 0);
        }
        
        $download = $this->Downloads->patchEntity($download, $download2->getData());
        if($this->Downloads->save($download))
        {
			
            $this->Flash->success(__('The download has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The download could not be {0}. Please try again.', $status));
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
            $download = $this->Downloads->get($id, [
                'conditions' => [
                    'Downloads.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid download selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions) = $this->_index();
        if($direction == 'up')
        {
            $respectRecord = $this->Downloads->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Downloads.lft <' => $download->lft])
                ->andWhere(['Downloads.download_category_id IS' => $download->download_category_id])
                ->order(['Downloads.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Downloads->find()
                    ->where(['download_category_id IS' => $download->download_category_id])
                    ->andWhere(['lft <' => $download->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Downloads->moveUp($download, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->Downloads->find()
                ->select(['lft'])
                ->where($conditions)
                ->andWhere(['Downloads.lft >' => $download->lft])
                ->andWhere(['Downloads.download_category_id IS' => $download->download_category_id])
                ->order(['Downloads.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Downloads->find()
                    ->where(['download_category_id IS' => $download->download_category_id])
                    ->andWhere(['lft >' => $download->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Downloads->moveDown($download, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The download has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The download could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
