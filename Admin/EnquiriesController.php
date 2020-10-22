<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class EnquiriesController extends AppController {

    public function initialize() {
        parent::initialize();
        if (!$this->userAuthorized()) {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }

    protected function _index() {
        $paramarters = [
            ['type', 'Enquiries.type', 'LIKE', ''],
            ['name', 'Enquiries.name', 'LIKE', ''],
            ['email', 'Enquiries.email', 'LIKE', ''],
            ['phone', 'Enquiries.phone', 'LIKE', ''],
            ['created_from', 'Enquiries.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Enquiries.created', 'lessThanOrEqual', 'DATE']
        ];

        $filters = $this->Search->formFilters($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Enquiries.is_deleted' => false,
        ];

        return [$conditions];
    }

    public function index() {
        $this->set('page_title', __('List Enquiries'));

        list($conditions) = $this->_index();
        $this->paginate = [
            'conditions' => $conditions,
            'order' => ['Enquiries.created' => 'DESC'],
            'sortWhitelist' => ['Enquiries.id', 'Enquiries.name', 'Enquiries.email', 'Enquiries.created']
        ];

        try {
            $enquiries = $this->paginate($this->Enquiries);
         
        } catch (NotFoundException $e) {
            $totalRecord = $this->Enquiries->find()
                    ->contain($contain)
                    ->where($conditions)
                    ->count();

            $pageCount = ceil(($totalRecord ? $totalRecord : 1) / $this->request->getParam('paging.' . $this->modelClass . '.perPage'));
            if ($pageCount > 1) {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
		$imageRoot = static::IMAGE_ROOT;
        $this->set(compact('enquiries','imageRoot'));
        $this->set('activeMenu', 'Admin.Enquiries.index');

        if ($this->request->is('ajax')) {
            $this->viewBuilder()
                    ->setLayout('ajax')
                    ->setTemplatePath('Element' . DS . 'Admin' . DS . 'Enquiries');
        }
    }

    public function delete($id = null) {
        try {
            $this->request->allowMethod(['post', 'delete']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $enquiry = $this->Enquiries->get($id, [
                'conditions' => [
                    'Enquiries.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid enquiry selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $enquiry->is_deleted = NULL;
        if ($this->Enquiries->save($enquiry)) {
			
            $this->Flash->success(__('The enquiry has been deleted successfully.'));
        } else {
            $this->Flash->error(__('The enquiry could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }

    public function export() {
        list($conditions) = $this->_index();
		
      
            $enquiries = $this->Enquiries->find()
                ->select(['id', 'name', 'email', 'phone', 'subject','message', 'type', 'created','dob','address','qualification','college','company_detail','interest','skill','language','week_no','travel','begin','experience','organization','location','internship','internship_nature','project','about_etasha','submitted_date','submitted_by'])
                ->where($conditions)
				->andWhere(['Enquiries.type'=>'volunteer'])
                ->order(['Enquiries.created' => 'DESC']);
				
		if(count($enquiries->toArray())<1) {
			return $this->redirect(['action' => 'index']);
		}
        $this->set(compact('enquiries'));

        $this->viewBuilder()->layout('default');
    }
     public function donation() {
        list($conditions) = $this->_index();
        $donations = $this->Enquiries->find()
                ->select(['id', 'name', 'email', 'phone', 'address','donations_material','drop_pickup','material_image', 'created'])
                ->where($conditions)
				->andWhere(['Enquiries.type'=>'donation'])
                ->order(['Enquiries.created' => 'DESC']);
        if(count($donations->toArray())<1) {
			return $this->redirect(['action' => 'index']);
		}
        $this->set(compact('donations'));

        $this->viewBuilder()->layout('default');
    }
	public function exportSubscribe() {
        list($conditions) = $this->_index();
        $enquiries = $this->Enquiries->find()
                ->select(['id', 'email', 'type', 'created'])
                ->where($conditions)
				->andWhere(['Enquiries.type'=>'subscribe'])
                ->order(['Enquiries.created' => 'DESC']);
		if(count($enquiries->toArray())<1) {
			return $this->redirect(['action' => 'index']);
		}
        $this->set(compact('enquiries'));

        $this->viewBuilder()->layout('default');
    }
    public function view($id = null){
        $this->autoRender = false;
        try {
            $enquiry = $this->Enquiries->get($id, [
                'conditions' => [
                    'Enquiries.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid enquiry selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $enquiry->open = true;
        
        if ($this->Enquiries->save($enquiry)) {
          
        } else {
            
        }
    }
    
  
}
