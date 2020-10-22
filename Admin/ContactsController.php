<?php
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class ContactsController extends AppController {

    public function initialize() {
        parent::initialize();
        if (!$this->userAuthorized()) {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }

    protected function _index() {
        $paramarters = [
            ['type', 'Contacts.type', 'LIKE', ''],
            ['name', 'Contacts.name', 'LIKE', ''],
            ['email', 'Contacts.email', 'LIKE', ''],
            ['phone', 'Contacts.phone', 'LIKE', ''],
            ['created_from', 'Contacts.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Contacts.created', 'lessThanOrEqual', 'DATE']
        ];

        $filters = $this->Search->formFilters($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Contacts.is_deleted' => false,
        ];

        return [$conditions];
    }

    public function index() {
        $this->set('page_title', __('List Contacts'));

        list($conditions) = $this->_index();
        $this->paginate = [
            'conditions' => $conditions,
            'order' => ['Contacts.created' => 'DESC'],
            'sortWhitelist' => ['Contacts.id', 'Contacts.name', 'Contacts.email', 'Contacts.created']
        ];

        try {
            $contacts = $this->paginate($this->Contacts);
         
        } catch (NotFoundException $e) {
            $totalRecord = $this->Contacts->find()
                    ->contain($contain)
                    ->where($conditions)
                    ->count();

            $pageCount = ceil(($totalRecord ? $totalRecord : 1) / $this->request->getParam('paging.' . $this->modelClass . '.perPage'));
            if ($pageCount > 1) {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }

        $this->set(compact('contacts'));
        $this->set('activeMenu', 'Admin.Contacts.index');

        if ($this->request->is('ajax')) {
            $this->viewBuilder()
                    ->setLayout('ajax')
                    ->setTemplatePath('Element' . DS . 'Admin' . DS . 'Contacts');
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
            $contact = $this->Contacts->get($id, [
                'conditions' => [
                    'Contacts.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid enquiry selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $contact->is_deleted = NULL;
        if ($this->Contacts->save($contact)) {
			$this->Flash->success(__('The enquiry has been deleted successfully.'));
        } else {
            $this->Flash->error(__('The enquiry could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }

    public function export() {
        list($conditions) = $this->_index();
        $contacts = $this->Contacts->find()
                ->where($conditions)
				->andWhere(['Contacts.type'=>'visit'])
                ->order(['Contacts.created' => 'DESC']);
		if(count($contacts->toArray())<1) {
			return $this->redirect(['action' => 'index']);
		}
        $this->set(compact('contacts'));
		
        $this->viewBuilder()->layout('default');
    }
     public function touchDetail() {
        list($conditions) = $this->_index();
        $contacts = $this->Contacts->find()
               ->where($conditions)
				->andWhere(['Contacts.type'=>'connect'])
                ->order(['Contacts.created' => 'DESC']);
		if(count($contacts->toArray())<1) {
			return $this->redirect(['action' => 'index']);
		}
        $this->set(compact('contacts'));

        $this->viewBuilder()->layout('default');
    }
    
    
  
}
