<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Network\Exception\NotFoundException;

class DownloadsController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['index']);
    }
    
    public function index()
    {
        $this->set('page_title', __('Downloads'));
		$this->loadModel('DownloadCategories');
		$activeDownloads = $this->DownloadCategories->Downloads->find()
            ->select(['download_category_id'])
            ->where(['Downloads.is_deleted' => false, 'Downloads.status' => true]);
        try {
            $this->loadModel('Pages');
            $page_content = $this->Pages->find()
                            ->select(['id','name','heading','content','parent_id','header_image'])
                            ->where(['Pages.slug' => 'downloads','Pages.is_deleted' => false])
                            ->first();
			
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
        
        $time = new Time();
        $conditions = [
            /* 'OR' => ['DownloadCategories.from_date IS' => NULL, 'DownloadCategories.from_date <=' => $time->format('Y-m-d')],
            'AND' => [
                'OR' => ['DownloadCategories.to_date IS' => NULL, 'DownloadCategories.to_date >=' => $time->format('Y-m-d')]
            ], */
			'DownloadCategories.id IN' => $activeDownloads,
			'DownloadCategories.is_deleted' => false,
            'DownloadCategories.status' => true,
            'DownloadCategories.parent_id IS' => NULL,
        ];
        
        $this->paginate = [
            'fields' => ['id','name'],
            'conditions' => $conditions,
             'order' => ['DownloadCategories.lft' => 'ASC'],
			 'limit' => 10
        ];
        
        try
        {
            $downloadCats = $this->paginate($this->DownloadCategories);
			
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->DownloadCategories->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
		$downloadArr=[];
        foreach($downloadCats as $downloadCat)
        {
            @$downloadArr[@$downloadCat->id] = $this->DownloadCategories->Downloads->find()
                ->select(['name', 'file_name'])
                ->where(['Downloads.download_category_id' => $downloadCat->id])
                ->andWhere(['Downloads.is_deleted' => false, 'Downloads.status' => true])
                ->order(['Downloads.lft' => 'ASC']); 
        }
		
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
        $this->set(compact('downloadArr', 'imageRoot', 'imageDir','page_content','downloadCats'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Downloads');
        }
    }
}
