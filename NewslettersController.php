<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Network\Exception\NotFoundException;

class NewslettersController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        $this->Auth->allow(['index']);
    }
    
    public function index()
    {
        $this->set('page_title', __('Newsletters'));
		$this->loadModel('NewsletterCategories');
		$activeDownloads = $this->NewsletterCategories->Newsletters->find()
            ->select(['newsletter_category_id'])
            ->where(['Newsletters.is_deleted' => false, 'Newsletters.status' => true]);
        try {
            $this->loadModel('Pages');
            $page_content = $this->Pages->find()
                            ->select(['id','name','heading','content','parent_id','header_image'])
                            ->where(['Pages.slug' => 'newsletter','Pages.is_deleted' => false])
                            ->first();
			
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
        
        $time = new Time();
        $conditions = [
           /*  'OR' => ['NewsletterCategories.from_date IS' => NULL, 'NewsletterCategories.from_date <=' => $time->format('Y-m-d')],
            'AND' => [
                'OR' => ['NewsletterCategories.to_date IS' => NULL, 'NewsletterCategories.to_date >=' => $time->format('Y-m-d')]
            ], */
			'NewsletterCategories.id IN' => $activeDownloads,
			'NewsletterCategories.is_deleted' => false,
            'NewsletterCategories.status' => true,
            'NewsletterCategories.parent_id IS' => NULL,
        ];
        
        $this->paginate = [
            'fields' => ['id','name'],
            'conditions' => $conditions,
             'order' => ['NewsletterCategories.lft' => 'ASC'],
			 'limit' => 10
        ];
        
        try
        {
            $newsCats = $this->paginate($this->NewsletterCategories);
			
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->NewsletterCategories->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
		$newsletterArr=[];
        foreach($newsCats as $newsCat)
        {
            @$newsletterArr[@$newsCat->id] = $this->NewsletterCategories->Newsletters->find()
                ->select(['name', 'file_name'])
                ->where(['Newsletters.newsletter_category_id' => $newsCat->id])
                ->andWhere(['Newsletters.is_deleted' => false, 'Newsletters.status' => true])
                ->order(['Newsletters.lft' => 'ASC']); 
        }
		
        $imageRoot = static::IMAGE_ROOT;
        $imageDir = static::IMAGE_DIR;
        $this->set(compact('newsletterArr', 'imageRoot', 'imageDir','page_content','newsCats'));
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Newsletters');
        }
    }
}
