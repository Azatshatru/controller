<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Utility\Hash;
use Cake\Network\Exception\NotFoundException;

class ImageCategoriesController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['index', 'view']);
    }
    
    public function index()
    {   
        $this->set('page_title', __('Photo Gallery'));
        
        $activeImages = $this->ImageCategories->ImageGalleries->find()
            ->select(['image_category_id'])
            ->where(['ImageGalleries.is_deleted' => false, 'ImageGalleries.status' => true]);
       
        $time = new Time();
        
        $conditions = [
            'ImageCategories.is_deleted' => false,
            'ImageCategories.status' => true,
            'ImageCategories.parent_id IS' => NULL,
            'OR' => ['ImageCategories.from_date IS' => NULL, 'ImageCategories.from_date <=' => $time->format('Y-m-d')],
            'AND' => [
                'OR' => ['ImageCategories.to_date IS' => NULL, 'ImageCategories.to_date >=' => $time->format('Y-m-d')]
            ],
            'ImageCategories.id IN' => $activeImages,
            'ImageCategories.show_in_gallery' => true
        ];
        $this->paginate = [
            'fields' => ['id', 'name', 'slug','category_image'],
            'conditions' => $conditions,
            'order' => ['ImageCategories.lft' => 'ASC'],
			'limit' =>24
        ];
        
        try
        {
            $albumCategories = $this->paginate($this->ImageCategories);
			//pr($albumCategories);exit;
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->ImageCategories->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        foreach($albumCategories as $imageCategory)
        {
            $activeImages->{$imageCategory->id} = $this->ImageCategories->ImageGalleries->find()
                ->select(['name', 'photo_image'])
                ->where(['ImageGalleries.image_category_id' => $imageCategory->id])
                ->andWhere(['ImageGalleries.is_deleted' => false, 'ImageGalleries.status' => true])
                ->order(['ImageGalleries.lft' => 'ASC'])
                ->first();
        }
      
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('albumCategories', 'activeImages', 'imageRoot'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'ImageCategories');
        }
    }
    
    public function view($slug = null,$school = null)
    {
         try
        {
            $time = new Time();
            $albumCategory = $this->ImageCategories->find()
                ->select(['id', 'name', 'slug', 'content',  'created'])
                ->where(['ImageCategories.is_deleted' => false, 'ImageCategories.status' => true, 'ImageCategories.parent_id IS' => NULL])
                ->andWhere(['ImageCategories.slug' => $slug])
                ->andWhere(['OR' => ['ImageCategories.from_date IS' => NULL, 'ImageCategories.from_date <=' => $time->format('Y-m-d')]])
                ->andWhere(['OR' => ['ImageCategories.to_date IS' => NULL, 'ImageCategories.to_date >=' => $time->format('Y-m-d')]])
                ->first();
            
            if(!$albumCategory)
            {
                throw new RecordNotFoundException(__('Record Not Found'));
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Album categoty you are looking for has been removed or is not available for now.'));
            return $this->redirect(['action' => 'index']);
        }
        
        $this->set('page_title', __('Photo Gallery:- {0}', $albumCategory->name));
        
        $conditions = [
            'ImageGalleries.image_category_id' => $albumCategory->id,
            'ImageGalleries.is_deleted' => false, 
            'ImageGalleries.status' => true
        ];
        
        $this->paginate = [
            'fields' => ['name', 'photo_image', 'created'],
            'conditions' => $conditions,
            'limit' => 18,
            'order' => ['ImageGalleries.sequence' => 'ASC']
        ];
        
        try
        {
            $this->loadModel('ImageGalleries');
            $images = $this->paginate($this->ImageGalleries);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->ImageCategories->ImageGalleries->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'album', $slug, 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'album', $slug]);
        }
        
        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('albumCategory', 'images', 'imageRoot','slug','school'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'ImageCategories');
        }
    }
   
}
