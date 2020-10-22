<?php
/**
 * @author: Sonia Solanki
 * @date: March 01, 2018
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class PagesController extends AppController
{
    const FILE_PATH = 'pages'.DS;
    const PAGE_MAXIMUM_LEVEL = 3;
    const PAGE_MAXIMUM_LEVEL1 = 7;
    
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
            ['name', 'Pages.name', 'LIKE', ''],
            ['slug', 'Pages.slug', 'LIKE', ''],
            ['institute_id', 'Pages.institute_id', 'EQUALS', ''],
            ['parent_id', 'Pages.lft', 'TREELIST', '', '', $this->Pages],
            ['status', 'Pages.status', 'EQUALS', ''],
            ['created_from', 'Pages.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Pages.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        if(!empty($this->navInstitutes)){
            $navSchoolcond = ['Institutes.id IN' => $this->navInstitutes];
        }else{
            $navSchoolcond = '';
        }
        $conditions = [
            $filters,
            'Pages.is_deleted' => false,
            'Institutes.is_deleted' => false,$navSchoolcond
        ];
        
        $contain = [
            'Institutes' => [
                'fields' => ['id', 'name', 'status']
            ],
            'ParentPages' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        
        return [$conditions, $contain];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Pages'));
        
        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'slug', 'institute_id', 'parent_id', 'status', 'lft', 'created'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['Pages.lft' => 'ASC'],
            'sortWhitelist' => ['Pages.id', 'Pages.name', 'Pages.slug', 'Pages.status', 'Pages.lft', 'Pages.created', 'Institutes.name', 'ParentPages.name']
        ];
        
        try
        {
            $pages = $this->paginate($this->Pages);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Pages->find()
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
        
        $institutes = $this->Pages->Institutes->find('list', [
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
        $treeList = $this->Pages->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->contain(['Institutes' => ['fields' => ['name']]])
            ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false]);
        
        $treeList2 = $this->Pages->find('list', [
            'valueField' => 'parent_id',
            'groupField' => 'institute_id'
        ])->contain($contain)
            ->where($conditions)
            ->order(['Pages.parent_id' => 'ASC', 'Pages.lft' => 'ASC']);
        
        if($this->request->getData('institute_id'))
        {
            $parents = $this->Pages->find('treeList', [
                'valuePath' => function($parents) {
                    return $parents->get('name').
                        ($parents->has('institute')?' ['.$parents->institute->get('name').']':'');
                }
            ])->select(['id', 'name', 'parent_id', 'status'])
                ->contain(['Institutes' => ['fields' => ['name']]])
                ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
                ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL - 1])
                ->andWhere(['Pages.institute_id' => $this->request->getData('institute_id')]);
            
            $treeList
                ->andWhere(['Pages.institute_id' => $this->request->getData('institute_id')]);
            
            $treeList2
                ->andWhere(['Pages.institute_id' => $this->request->getData('institute_id')]);
        }
        else
        {
            $parents = [];
            foreach($institutes as $recordId => $recordName)
            {
                $parents += $this->Pages->find('treeList', [
                    'valuePath' => function($options2) {
                        return $options2->get('name').
                            ($options2->has('institute')?' ['.$options2->institute->get('name').']':'');
                    }
                ])->select(['id', 'name', 'parent_id', 'status'])
                    ->contain(['Institutes' => ['fields' => ['name']]])
                    ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
                    ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL - 1])
                    ->andWhere(['Pages.institute_id' => $recordId])
                    ->toArray();
            }
        }
        
        $pageMaximumLevel = static::PAGE_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('pages', 'institutes', 'parents', 'treeList', 'treeList2', 'pageMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.Pages.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Pages');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New Page'));
        
        $page = $this->Pages->newEntity();
        if($this->request->is('post'))
        {
            $page2 = $this->request->withData('is_deleted', 0);
            $page = $this->Pages->patchEntity($page, $page2->getData(), [
                'accessibleFields' => ['institute_id' => true]
            ]);
            if(!$page->errors())
            {
                $errors = [];
                if($this->request->getData('header_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('header_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('header_file.name'), PATHINFO_EXTENSION));
                    $page->header_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$page->header_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $page->header_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$page->header_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('header_file.tmp_name'), static::IMAGE_ROOT.$page->header_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload header image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    $this->Pages->behaviors()->Tree->config('scope', ['institute_id' => $this->request->getData('institute_id')]);
                    if($this->Pages->save($page))
                    { $this->call_admin();
                        $this->Flash->success(__('The page has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                    else
                    {
                        $this->Flash->error(__('The page could not be added. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The page could not be added. Please see warning(s) below.'));
            }
        }
        
        $institutes = $this->Pages->Institutes->find('list', [
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
        $parents = $this->Pages->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->contain(['Institutes' => ['fields' => ['name']]])
            ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
            ->andWhere(['Pages.institute_id' => $this->request->getData('institute_id')])
            ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL - 1]);
        
        $pageMaximumLevel = static::PAGE_MAXIMUM_LEVEL;
        $this->set(compact('page', 'institutes', 'parents', 'pageMaximumLevel'));
        $this->set('activeMenu', 'Admin.Pages.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit Page'));
        
        try
        {
            $page = $this->Pages->get($id, [
                'contain' => [
                    'Institutes' => [
                        'fields' => ['id', 'name', 'status']
                    ],
                    'ParentPages' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'Pages.is_deleted' => false,
                    'Institutes.is_deleted' => false
                ]
            ]);
            
            $pageDb = clone $page;
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid page selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $page2 = $this->request
                ->withData('institute_id', $pageDb->institute_id)
                ->withData('is_deleted', $page->is_deleted);
            $page = $this->Pages->patchEntity($page, $page2->getData());
            if(!$page->errors())
            {
                $errors = [];
                if($this->request->getData('header_file.tmp_name'))
                {
                    $fileName = strtolower(pathinfo($this->request->getData('header_file.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('header_file.name'), PATHINFO_EXTENSION));
                    $page->header_image = static::FILE_PATH.$fileName.'.'.$fileExtension;
                    
                    $file = new File(static::IMAGE_ROOT.$page->header_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.static::FILE_PATH);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $page->header_image = static::FILE_PATH.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$page->header_image);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('header_file.tmp_name'), static::IMAGE_ROOT.$page->header_image);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload header image. Please try again.');
                    }
                }
                
                if(empty($errors))
                {
                    $this->Pages->behaviors()->Tree->config('scope', ['institute_id' => $page->institute_id]);
                    if($this->Pages->save($page))
                    { 
				        $this->call_admin();
                        $this->Flash->success(__('The page has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                    else
                    {
                        $this->Flash->error(__('The page could not be updated. Please see warning(s) below.'));
                    }
                }
                else
                {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            }
            else
            {
                $this->Flash->error(__('The page could not be updated. Please see warning(s) below.'));
            }
        }
        
        $institutes = $this->Pages->Institutes->find('list', [
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
        $parents = $this->Pages->find('treeList')
            ->select(['id', 'name', 'parent_id', 'status'])
            ->contain(['Institutes' => ['fields' => ['name']]])
            ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
            ->andWhere(['Pages.institute_id' => $page->institute_id])
            ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL1 - 1])
            ->andWhere(function($exp, $q) use($pageDb) {
                return $exp->not([
                    $q->newExpr()->between('Pages.lft', $pageDb->lft, $pageDb->rght)
                ]);
            });
        /* $parents = $this->Pages->find('treeList')
					->select(['id', 'name', 'parent_id', 'status'])
					 ->where(['Pages.is_deleted' => false])
					->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL - 1]); */
        $pageMaximumLevel = static::PAGE_MAXIMUM_LEVEL;
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('page', 'institutes', 'parents', 'pageMaximumLevel', 'imageRoot'));
        $this->set('activeMenu', 'Admin.Pages.index');
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
            $page = $this->Pages->get($id, [
                'contain' => [
                    'Institutes' => [
                        'fields' => ['id', 'name', 'status']
                    ],
                    'ParentPages' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'Pages.is_deleted' => false,
                    'Institutes.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid page selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $page->is_deleted = NULL;
        if($this->Pages->save($page))
        {
			$this->call_admin();
            $this->Pages->query()
                ->update()
                ->set(['is_deleted' => NULL])
                ->where(function($exp, $q) use($page) {
                    return $exp->between('Pages.lft', $page->lft+1, $page->rght-1);
                })
                ->andWhere(['Pages.is_deleted' => false])
                ->execute();
            
            $this->Flash->success(__('The page and all its child has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The page could not be deleted. Please try again.'));
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
            $page = $this->Pages->get($id, [
                'contain' => [
                    'Institutes' => [
                        'fields' => ['id', 'name', 'status']
                    ],
                    'ParentPages' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'Pages.is_deleted' => false,
                    'Institutes.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid page selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $page2 = $this->request->withData('status', 1);
        }
        else
        {
            $status = 'inactive';
            $page2 = $this->request->withData('status', 0);
        }
        
        $page = $this->Pages->patchEntity($page, $page2->getData());
        if($this->Pages->save($page))
        {   $this->call_admin();
            $this->Flash->success(__('The page has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The page could not be {0}. Please try again.', $status));
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
            $page = $this->Pages->get($id, [
                'contain' => [
                    'Institutes' => [
                        'fields' => ['id', 'name', 'status']
                    ],
                    'ParentPages' => [
                        'fields' => ['id', 'name', 'status']
                    ]
                ],
                'conditions' => [
                    'Pages.is_deleted' => false,
                    'Institutes.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid page selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        list($conditions, $contain) = $this->_index();
        $this->Pages->behaviors()->Tree->config('scope', ['institute_id' => $page->institute_id]);
        if($direction == 'up')
        {
            $respectRecord = $this->Pages->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['Pages.lft <' => $page->lft])
                ->andWhere(['Pages.parent_id IS' => $page->parent_id])
                ->order(['Pages.lft' => 'DESC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Pages->find()
                    ->where(['parent_id IS' => $page->parent_id])
                    ->andWhere(['lft <' => $page->lft, 'lft >=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Pages->moveUp($page, $moveCount);
					
                }
            }
        }
        else
        {
            $respectRecord = $this->Pages->find()
                ->select(['lft'])
                ->contain($contain)
                ->where($conditions)
                ->andWhere(['Pages.lft >' => $page->lft])
                ->andWhere(['Pages.parent_id IS' => $page->parent_id])
                ->order(['Pages.lft' => 'ASC'])
                ->first();
            
            if($respectRecord)
            {
                $moveCount = $this->Pages->find()
                    ->where(['parent_id IS' => $page->parent_id])
                    ->andWhere(['lft >' => $page->lft, 'lft <=' => $respectRecord->lft])
                    ->count();
                
                if($moveCount)
                {
                    $result = $this->Pages->moveDown($page, $moveCount);
                }
            }
        }
        
        if($respectRecord && $moveCount && $result)
        {
            $this->Flash->success(__('The page has been moved {0} successfully.', $direction));
        }
        else
        {
            $this->Flash->error(__('The page could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up')?'first':'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    public function fillPages()
    {
		
		
        try
        {
            if(!$this->request->is('ajax'))
            {
                throw new BadRequestException();
            }
        }
        catch(BadRequestException $e)
        {
            $this->Flash->error(__('Only ajax request can be processed.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->getData('requester') != 'grid' || $this->request->getData('institute_id') != '')
        {
            $options2 = $this->Pages->find('treeList', [
                'valuePath' => function($options2) {
                    return $options2->get('name').
                        (($this->request->getData('requester') == 'grid' && $options2->has('institute'))?' ['.$options2->institute->get('name').']':'');
                }
            ])->select(['id', 'name', 'parent_id', 'status'])
                ->contain(['Institutes' => ['fields' => ['name']]])
                ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
                ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL1 - 1])
                ->andWhere(['Pages.institute_id' => $this->request->getData('institute_id')]);
            
            if($this->request->getData('requester') == 'update')
            {
                $page = $this->Pages->find()
                    ->select(['lft', 'rght'])
                    ->where(['id' => $this->request->getData('id')])
                    ->first();
                
                if($page)
                {
                    $options2
                        ->andWhere(function($exp, $q) use($page) {
                            return $exp->not([
                                $q->newExpr()->between('Pages.lft', $page->lft, $page->rght)
                            ]);
                        });
                }
            }
            
            $options2 = $options2->toArray();
        }
        else
        {
            $institutes = $this->Pages->Institutes->find('list', [
                'valueField' => function($institutes) {
                    return $institutes->get('name').(!$institutes->get('status')?' (x)':'');
                }
            ])->select(['id', 'name', 'status'])
                ->where(['Institutes.is_deleted' => false])
                ->order(['Institutes.name' => 'ASC']);
            
            $options2 = [];
            foreach($institutes as $recordId => $recordName)
            {
                $options2 += $this->Pages->find('treeList', [
                    'valuePath' => function($options2) {
                        return $options2->get('name').
                            (($this->request->getData('requester') == 'grid' && $options2->has('institute'))?' ['.$options2->institute->get('name').']':'');
                    }
                ])->select(['id', 'name', 'parent_id', 'status'])
                    ->contain(['Institutes' => ['fields' => ['name']]])
                    ->where(['Pages.is_deleted' => false, 'Institutes.is_deleted' => false])
                    ->andWhere(['Pages.level <' => static::PAGE_MAXIMUM_LEVEL1 - 1])
                    ->andWhere(['Pages.institute_id' => $recordId])
                    ->toArray();
            }
        }
        
        if($this->request->getData('requester') == 'grid')
        {
            $options = ['' => __('All Pages')] + $options2;
        }
        else
        {
            $options = ['' => __('Parent Page')] + $options2;
        }
         
        $this->set(compact('options'));
        $this->set('_serialize', ['options']);
        
        $this->render(DS.'Element'.DS.'Ajax'.DS.'options');
    }
    
    public function uploads(){
        $this->autoRender = false;
        if($this->request->getData('image.tmp_name'))
                {
                    $filepath = 'editor'.DS;
                    $fileName = strtolower(pathinfo($this->request->getData('image.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('image.name'), PATHINFO_EXTENSION));
                    $image_url = $filepath.$fileName.'.'.$fileExtension;
                    $file = new File(static::IMAGE_ROOT.$image_url);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT.$filepath);
                    
                    $i = 1;
                    while($file->exists())
                    {
                        $image_url = $filepath.$fileName.' ('.$i++.').'.$fileExtension;
                        $file = new File(static::IMAGE_ROOT.$image_url);
                    }
                    
                    $success = move_uploaded_file($this->request->getData('image.tmp_name'), static::IMAGE_ROOT.$image_url);
                    if(!$success)
                    {
                        $errors[] = __('Unable to upload image. Please try again.');
                    }
                    
                    echo $url = static::IMAGE_DIR.$image_url;
                }
                else{
                    echo $url = '';
                }
    }
}
