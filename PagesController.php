<?php
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Network\Exception\ForbiddenException;
use Cake\Network\Exception\NotFoundException;
use Cake\View\Exception\MissingTemplateException;
use Cake\Utility\Hash;
use Cake\Filesystem\File;

class PagesController extends AppController {

    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index','fillLevels','visitorCounter','testimonials','fillCourses','fillCities']);
    }

    public function index(...$path) {

        $count = count($path);
        if (!$count) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
       if(!empty($path[1])){
                $condition = ['Pages.slug' => $subpage,'Pages.is_deleted' => false];
        }else{
                $condition = ['Pages.slug' => $page,'Pages.is_deleted' => false];
        }
       $this->set(compact('page', 'subpage'));

        try {
            $page_content = $this->Pages->find()
                            ->select(['id','name','slug','heading','content','parent_id','meta_title','meta_keywords','meta_description','header_image'])
                            ->contain([
                                'ParentPages' => ['fields' => ['id','name','heading','slug']],
                                'ChildPages' => [
                                    'fields' => ['parent_id','name','slug'],
                                    'conditions' => ['ChildPages.is_deleted' => false]
                                    ]])
                            ->where($condition)
                            ->first();
            
//            if($page_content){
//                $related_pages = $this->Pages->find()
//                    ->select(['id','name','slug'])
//                    ->where(['Pages.parent_id' => $page_content->parent_id,])
//                    ->orwhere(['Pages.parent_id' => $page_content->id])
//                    ->orwhere(['Pages.id' => $page_content->id])
//                    ->orwhere(['Pages.id' => $page_content->parent_id])
//                    ->andwhere(['Pages.is_deleted' => false,'Pages.content !=' => ''])
//                    ->toArray();
//            }else{
//                $related_pages = '';
//            }
            //$this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
		  
		
		
        if(!$page_content){
		return $this->redirect(['controller' => 'Page404', 'action' => 'index']);
	   }else{
		   $page_type=0;
		$check = $this->Pages->find('children',['for'=>$page_content->id])->toArray();
		$check1 = $this->Pages->find('path',['for'=>$page_content->id])->toArray();
	    if(count($check)>0 || count($check1)>1)
        {
			$page_type=1; 
			if(!empty($check1[0]->id)){
			$child_pages = $this->Pages->find('children',['for'=>@$check1[0]->id])
                           ->toArray();
			 } 
		}
	   }
	 $this->loadModel('Courses');
        $coursesList = $this->Courses->find('list')
                    ->contain(['Institutes','LevelCodes'])
                    ->where(['Courses.is_deleted' => false,'Courses.status' => true,'Institutes.is_deleted' => false,'Institutes.status' => true,'LevelCodes.is_deleted' => false,'LevelCodes.status' => true]);
        $this->set(compact('page_content','page_type','childPage','check1','child_pages','coursesList'));
    }
    
    public function fillLevels()
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
 
       $this->loadModel('Courses');
      
       $matchingLevels = $this->Courses->find()
            ->select(['level_id'])->distinct()
            ->contain(['Institutes'])
            ->where(['Courses.is_deleted' => false,'Courses.status' => true,])
            ->andWhere(['Institutes.is_deleted' => false,'Institutes.status' => true,])
            ->andWhere(['Courses.institute_id' => $this->request->getData('institute_id')]);
       $options2 = $this->Courses->LevelCodes->find('list')
            ->select(['id', 'name', 'status'])
            ->contain(['CodeCategories'])
            ->where(['LevelCodes.is_deleted' => false, 'CodeCategories.name' => 'Levels'])
            ->andWhere(['LevelCodes.id IN ' => $matchingLevels])
            ->order(['LevelCodes.sort' => 'ASC']);
       
      
        if($this->request->getData('requester') == 'grid')
        {
            $options = ['' => __('All Levels')] + $options2->toArray();
        }
        else
        {
            $options = ['' => __('Select One')] + $options2->toArray();
        }
        
        $this->set(compact('options'));
        
        $this->render(DS.'Element'.DS.'Ajax'.DS.'options');
    }
    
    public function fillCourses()
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
        $this->loadModel('Courses');
        $options2 = $this->Courses->find('list')
            ->select(['id', 'name', 'status'])
            ->contain(['Institutes'])
            ->where([
                'Courses.is_deleted' => false, 'Institutes.is_deleted' => false
            ])
            ->andWhere([
                'Courses.status' => true, 'Institutes.status' => true
            ])
            ->order(['Courses.name' => 'ASC']);
        
        if($this->request->getData('requester') != 'grid' || $this->request->getData('institute_id') != '')
        {
            $options2->andWhere(['Courses.institute_id' => $this->request->getData('institute_id')]);
        }
        
        if($this->request->getData('requester') == 'grid')
        {
            $options = ['' => __('All Courses')] + $options2->toArray();
        }
        else
        {
            $options = ['' => __('Select One')] + $options2->toArray();
        }
        
        $this->set(compact('options'));
        
        $this->render(DS.'Element'.DS.'Ajax'.DS.'options');
    }
    
     public function fillCities()
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
        $this->loadModel('Cities');
        $options2 = $this->Cities->find('list')
            ->select(['id', 'name', 'status'])
            ->contain(['States.CountryCodes'])
            ->where(['Cities.is_deleted' => false, 'States.is_deleted' => false, 'CountryCodes.is_deleted' => false])
            ->andWhere(['Cities.status' => true, 'States.status' => true, 'CountryCodes.status' => true])
            ->andWhere(['Cities.state_id' => $this->request->getData('state_id')])
            ->order(['Cities.name' => 'ASC']);
       
        $options = ['' => __('Select City')] + $options2->toArray();
        
        
        $this->set(compact('options'));
        
        $this->render(DS.'Element'.DS.'Ajax'.DS.'options');
    }

     public function visitorCounter()
    {
        $file = new File(WWW_ROOT.'count.txt');
        $numberCounter = (int) $file->read();
        
        if($this->request->getCookie('isVisited'))
        {
            $this->response = $this->response->withCookie('isVisited', [
                'value' => 'yes',
                'path' => '/',
                'httpOnly' => true,
                'secure' => false,
                'expire' => strtotime('+24 hours')
            ]);
        }
        else
        {
            $numberCounter++;
            $file->write($numberCounter);
            
            $this->response = $this->response->withCookie('isVisited', [
                'value' => 'yes',
                'path' => '/',
                'httpOnly' => true,
                'secure' => false,
                'expire' => strtotime('+24 hours')
            ]);
        }
        
        $this->set(compact('numberCounter'));
        
        $this->viewBuilder()->setLayout('ajax');
    }
    
    public function testimonials(){
         // Testimonials
        $this->loadModel('Testimonials');
        $testimonials = $this->Testimonials->find()
                        ->select(['name','profile_image','content'])
                        ->where(['Testimonials.is_deleted' => false,'Testimonials.status' => true])
                        ->order(['Testimonials.lft' => 'ASC']);
        $this->set(compact('testimonials'));
    }
   
}
