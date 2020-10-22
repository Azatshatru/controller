<?php
namespace App\Controller;
use Cake\Utility\Hash;
use Cake\I18n\Time;

class NewsController extends AppController {

    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index','view']);
    }

    public function index() {
        $this->set('page_title', __('News'));
        
        $time = new Time();
        $this->loadModel('News');
        $news = $this->paginate($this->News->find()
				->where(['News.is_deleted' => false, 'News.status' => true])
				->andWhere(['OR' => ['News.from_date IS' => NULL, 'News.from_date <=' => $time->format('Y-m-d')]])
				->andWhere(['OR' => ['News.to_date IS' => NULL, 'News.to_date >=' => $time->format('Y-m-d')]])
				->order(['News.lft' => 'ASC']));
        
        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('news', 'imageRoot','imagePath'));
		if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'News');
        }
    }
    
    public function view($slug = null){
        $time = new Time();
        try
        {
            $news = $this->News->find()
                ->select(['id', 'name', 'slug', 'content', 'news_image', 'created','meta_title','meta_keyword','meta_description'])
                ->where(['News.is_deleted' => false, 'News.status' => true])
                ->andWhere(['OR' => ['News.from_date IS' => NULL, 'News.from_date <=' => $time->format('Y-m-d')]])
                ->andWhere(['OR' => ['News.to_date IS' => NULL, 'News.to_date >=' => $time->format('Y-m-d')]])
                ->andWhere(['News.slug' => $slug])
                ->first();
            
            if(!$news)
            {
                throw new RecordNotFoundException(__('Record Not Found'));
            }
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('blog you are looking for has been removed or is not available for now.'));
            return $this->redirect(['action' => 'index']);
        }
        
        // related news 
        $relatednews = $this->News->find()
                        ->select(['id','name','slug','content','news_image','created','from_date'])
                        ->where(['News.is_deleted' => false,'News.status' => true,'News.id !=' => @$news->id])
                        ->andWhere(['OR' => ['News.from_date IS' => NULL, 'News.from_date <=' => $time->format('Y-m-d')]])
                        ->andWhere(['OR' => ['News.to_date IS' => NULL, 'News.to_date >=' => $time->format('Y-m-d')]])
                        ->order(['News.lft' => 'ASC'])
                        ->limit(8);
            
           
        if($news->image_category_id!=''){
            $this->loadModel("ImageGalleries");
            $news_gallery = $this->ImageGalleries->find()
                    ->select(['name', 'photo_image', 'created'])
                    ->where(['ImageGalleries.is_deleted' => false,'ImageGalleries.status' => true,'ImageGalleries.image_category_id' => $news->image_category_id])
                    ->order(['ImageGalleries.lft' => 'ASC']);
        }else{
            $news_gallery = '';
        }
       
        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('news', 'imageRoot','relatednews','news_gallery','imagePath'));
    }
}
