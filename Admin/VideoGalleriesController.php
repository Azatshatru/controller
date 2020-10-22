<?php
/**
 * @author: Manoj Tanwar
 * @date: Apr 25, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class VideoGalleriesController extends AppController
{
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
            ['name', 'VideoGalleries.name', 'LIKE', ''],
            ['url', 'VideoGalleries.url', 'LIKE', ''],
            ['video_category_id', 'VideoCategories.lft', 'TREELISTCURRENT', '', '', $this->VideoGalleries->VideoCategories],
            ['status', 'VideoGalleries.status', 'EQUALS', ''],
            ['created_from', 'VideoGalleries.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'VideoGalleries.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'VideoGalleries.is_deleted' => false,
            //'VideoCategories.is_deleted' => false
        ];
        
        $contain = [
            'VideoCategories' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Gallery Videos'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'video_category_id', 'url', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['VideoGalleries.lft' => 'ASC'],
            'sortWhitelist' => ['VideoGalleries.id', 'VideoGalleries.name', 'VideoGalleries.url', 'VideoGalleries.status', 'VideoGalleries.lft', 'VideoGalleries.created', 'VideoCategories.name']
        ];
        
        try
        {
            $videoGalleries = $this->paginate($this->VideoGalleries);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->VideoGalleries->find()
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
        
        $treeList = $this->VideoGalleries->find('list', [
            'valueField' => 'video_category_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['VideoGalleries.video_category_id' => 'ASC', 'VideoGalleries.lft' => 'ASC']);
        
        $videoCategories = $this->VideoGalleries->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL]);
        
        foreach($videoCategories as $key => $value)
        {
            $categoryPath[$key] = $this->VideoGalleries->VideoCategories->find('path', ['for' => $key])
                ->select(['id', 'name']);
        }
        
        $this->set(compact('videoGalleries', 'videoCategories', 'treeList', 'categoryPath'));
        $this->set('activeMenu', 'Admin.VideoGalleries.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'VideoGalleries');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Gallery Video'));
        
        $videoGallery = $this->VideoGalleries->newEntity();
        if($this->request->is('post'))
        {
            $videoGallery2 = $this->request->withData('is_deleted', 0);
            $videoGallery = $this->VideoGalleries->patchEntity($videoGallery, $videoGallery2->getData());
            $this->VideoGalleries->behaviors()->Tree->config('scope', ['video_category_id' => $this->request->getData('video_category_id')]);
            if($this->VideoGalleries->save($videoGallery))
            {
				if($this->request->getData('top_on_list'))
                {
                    $this->VideoGalleries->moveUp($videoGallery, true);
                }
                
                $this->Flash->success(__('The gallery video has been added successfully.'));
                return $this->redirect(['action' => 'index']);
            }
            else
            {
                $this->Flash->error(__('The gallery video could not be added. Please see warning(s) below.'));
            }
        }
        
        $videoCategories = $this->VideoGalleries->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL]);
        
        $this->set(compact('videoGallery', 'videoCategories'));
        $this->set('activeMenu', 'Admin.VideoGalleries.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Gallery Video'));
        
        try
        {
            $videoGallery = $this->VideoGalleries->get($id, [
                'contain' => [
                    'VideoCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'VideoGalleries.is_deleted' => false,
                    //'VideoCategories.is_deleted' => false
                ]
            ]);
            
            $videoGalleryDb = clone $videoGallery;
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery video selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $videoGallery2 = $this->request->withData('is_deleted', $videoGallery->is_deleted);
            $videoGallery = $this->VideoGalleries->patchEntity($videoGallery, $videoGallery2->getData());
            if(!$videoGallery->errors())
            {
                if($this->request->getData('video_category_id') != $videoGalleryDb->video_category_id)
                {
                    $this->VideoGalleries->behaviors()->Tree->config('scope', ['video_category_id' => $videoGalleryDb->video_category_id]);
                    $this->VideoGalleries->moveDown($videoGalleryDb, true);

                    $videoCount = $this->VideoGalleries->find()
                        ->where(['video_category_id' => $this->request->getData('video_category_id')])
                        ->count();
                    
                    $videoGallery2 = $this->request
                        ->withData('lft', ($videoCount*2)+1)
                        ->withData('rght', ($videoCount*2)+2);
                    
                    $videoGallery = $this->VideoGalleries->patchEntity($videoGallery, $videoGallery2->getData(), [
                        'accessibleFields' => ['lft' => true, 'rght' => true]
                    ]);
                }
                
                if($this->VideoGalleries->save($videoGallery))
                {
					$this->Flash->success(__('The gallery video has been updated successfully.'));
                    return $this->redirect($this->_redirectUrl());
                }
                else
                {
                    $this->Flash->error(__('The gallery video could not be updated. Please see warning(s) below.'));
                }
            }
            else
            {
                $this->Flash->error(__('The gallery video could not be updated. Please see warning(s) below.'));
            }
        }
        
        $videoCategories = $this->VideoGalleries->VideoCategories->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->where(['VideoCategories.is_deleted' => false])
            ->andWhere(['VideoCategories.level <' => static::VIDEO_MAXIMUM_LEVEL]);
        
        $this->set(compact('videoGallery', 'videoCategories'));
        $this->set('activeMenu', 'Admin.VideoGalleries.index');
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
            $videoGallery = $this->VideoGalleries->get($id, [
                'contain' => [
                    'VideoCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'VideoGalleries.is_deleted' => false,
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery video selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $videoGallery->is_deleted = NULL;
        if($this->VideoGalleries->save($videoGallery))
        {
			$this->Flash->success(__('The gallery video has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The gallery video could not be deleted. Please try again.'));
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
            $videoGallery = $this->VideoGalleries->get($id, [
                'contain' => [
                    'VideoCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'VideoGalleries.is_deleted' => false,
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery video selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $videoGallery2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $videoGallery2 = $this->request->withData('status', 0);
        }
        
        $videoGallery = $this->VideoGalleries->patchEntity($videoGallery, $videoGallery2->getData());
        if($this->VideoGalleries->save($videoGallery))
        {
			$this->Flash->success(__('The gallery video has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The gallery video could not be {0}. Please try again.', $status));
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
            $videoGallery = $this->VideoGalleries->get($id, [
                'contain' => [
                    'VideoCategories' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'VideoGalleries.is_deleted' => false,
                    'VideoCategories.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid gallery video selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        $this->VideoGalleries->behaviors()->Tree->config('scope', ['video_category_id' => $videoGallery->video_category_id]);
        if($direction == 'up')
        {
            $respectRecord = $this->VideoGalleries->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['VideoGalleries.lft <' => $videoGallery->lft])
                ->andWhere(['VideoGalleries.video_category_id IS' => $videoGallery->video_category_id])
                ->order(['VideoGalleries.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->VideoGalleries->find()
                    ->where(['video_category_id IS' => $videoGallery->video_category_id])
                    ->andWhere(['lft <' => $videoGallery->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->VideoGalleries->moveUp($videoGallery, $moveCount);
                }
            }
        }
        else
        {
            $respectRecord = $this->VideoGalleries->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['VideoGalleries.lft >' => $videoGallery->lft])
                ->andWhere(['VideoGalleries.video_category_id IS' => $videoGallery->video_category_id])
                ->order(['VideoGalleries.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->VideoGalleries->find()
                    ->where(['video_category_id IS' => $videoGallery->video_category_id])
                    ->andWhere(['lft >' => $videoGallery->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->VideoGalleries->moveDown($videoGallery, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The gallery video has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The gallery video could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
}
