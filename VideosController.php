<?php

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Network\Exception\NotFoundException;
use Cake\I18n\Time;

class VideosController extends AppController {

    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index']);
    }

    public function index($slug = null) {
        $this->set('page_title', __('Video Gallery'));
        $this->loadModel('VideoGalleries');
        $time = new Time();
        $conditions = [
            'VideoGalleries.is_deleted' => false,
            'VideoGalleries.status' => true,
        ];
       
        $this->paginate = [
            'fields' => ['id', 'name', 'url'],
            'conditions' => $conditions,
            'order' => ['VideoGalleries.lft' => 'ASC'],
			'limit' => 28
        ];
        
        try
        {
            $videos = $this->paginate($this->VideoGalleries); 
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->VideoGalleries->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }

        $imageRoot = static::IMAGE_ROOT;
        $this->set(compact('videos', 'imageRoot'));
        
    }

}
