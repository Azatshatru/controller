<?php

/**
 * @author: Sonia Solanki
 * @date: Oct 15,2018
 * @version: 1.0.0
 */

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Filesystem\File;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\I18n\Time;

class TcertificatesController extends AppController {

    const FILE_PATH = 'tc' . DS;

    public function initialize() {
        parent::initialize();

        if (!$this->userAuthorized()) {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }

    protected function _index() {
        $paramarters = [
            ['scholar_no', 'Tcertificates.scholar_no', 'LIKE', ''],
            ['student_name', 'Tcertificates.student_name', 'LIKE', ''],
            ['session_id', 'Sessions.id', 'LIKE', ''],
            ['class_id', 'Classes.id', 'LIKE', ''],
            ['dob', 'Tcertificates.dob', 'EQUALS', 'DATE'], 
            ['created_from', 'Tcertificates.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Tcertificates.created', 'lessThanOrEqual', 'DATE']
        ];

        $filters = $this->Search->formFilters($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Tcertificates.is_deleted' => false,
            'Classes.is_deleted' => false,
            'Sessions.is_deleted' => false,
        ];

        $contain = [
            'Classes' => [
                'fields' => ['id', 'name', 'status']
            ],
            'Sessions' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];

        return [$conditions, $contain];
    }

    public function index() {
        $this->set('page_title', __('List Transfer Certificates'));

        list($conditions, $contain) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'student_name', 'scholar_no', 'dob', 'class_id', 'tc_image', 'session_id', 'created', 'status'],
            'contain' => $contain,
            'conditions' => $conditions,
            'order' => ['Tcertificates.created' => 'DESC'],
            'sortWhitelist' => ['Tcertificates.id', 'Tcertificates.student_name', 'Tcertificates.created', 'Classes.name', 'Sessions.name']
        ];

        try {
            $tcertificates = $this->paginate($this->Tcertificates);
        } catch (NotFoundException $e) {
            $totalRecord = $this->Tcertificates->find()
                    ->contain($contain)
                    ->where($conditions)
                    ->count();

            $pageCount = ceil(($totalRecord ? $totalRecord : 1) / $this->request->getParam('paging.' . $this->modelClass . '.perPage'));
            if ($pageCount > 1) {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }

        $classes = $this->Tcertificates->Classes->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Classes.is_deleted' => false])
                ->order(['Classes.lft' => 'ASC']);

        $sessions = $this->Tcertificates->Sessions->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Sessions.is_deleted' => false])
                ->order(['Sessions.lft' => 'ASC']);

        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('tcertificates', 'classes', 'sessions', 'imageRoot','imagePath'));
        $this->set('activeMenu', 'Admin.Tcertificates.index');

        if ($this->request->is('ajax')) {
            $this->viewBuilder()
                    ->setLayout('ajax')
                    ->setTemplatePath('Element' . DS . 'Admin' . DS . 'Tcertificates');
        }
    }

    public function add() {
        $this->set('page_title', __('Add New Transfer Certificates'));

        $tcertificates = $this->Tcertificates->newEntity();
        if ($this->request->is('post')) {
            if ($this->request->getData('tcertificates')) {
                foreach ($this->request->getData('tcertificates') as $key => $tc) {
                    $errors = [];
                    if ($this->request->getData('tcertificates.' . $key . '.tc_image.tmp_name')) {
                        $fileName = strtolower(pathinfo($this->request->getData('tcertificates.' . $key . '.tc_image.name'), PATHINFO_FILENAME));
                        $fileExtension = strtolower(pathinfo($this->request->getData('tcertificates.' . $key . '.tc_image.name'), PATHINFO_EXTENSION));
                        $file_name = static::FILE_PATH . $fileName . '.' . $fileExtension;

                        $file = new File(static::IMAGE_ROOT . $file_name);
                        $directory = $file->folder();
                        $directory->create(static::IMAGE_ROOT . static::FILE_PATH);

                        $i = 1;
                        while ($file->exists()) {
                            $file_name = static::FILE_PATH . $fileName . ' (' . $i++ . ').' . $fileExtension;
                            $file = new File(static::IMAGE_ROOT . $file_name);
                        }

                        $success = move_uploaded_file($this->request->getData('tcertificates.' . $key . '.tc_image.tmp_name'), static::IMAGE_ROOT . $file_name);
                        if (!$success) {
                            $errors[] = __('Unable to upload {0}. Please try again.');
                        }
                    }
                    if (empty($errors)) {
                        $data[] = [
                            'scholar_no' => $tc['scholar_no'],
                            'student_name' => $tc['student_name'],
                            'tc_image' => $file_name,
                            'is_deleted' => 0,
                            'status' => $this->request->getData('status'),
                            'session_id' => $this->request->getData('session_id'),
                            'class_id' => $this->request->getData('class_id'),
                            'dob' => $tc['dob'],
                        ];
                    } else {
                        $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                    }
                }

                $tcertificates = $this->Tcertificates->newEntities($data);
               $err = array();
                foreach($tcertificates as $k => $tcerti){
                    if (!$tcerti->errors()) {} 
                    else{
                        $errors[] = $tcerti->errors();
                    }
                }
                if(!empty($errors)){
                    foreach($errors as $key => $error){
                       foreach($error as $key1 => $err){
                           $rr = implode(',', $err); 
                           $err1[] = $rr;
                        }
                    }
                }
                
                if (empty($err1)) {
                    if ($this->Tcertificates->saveMany($tcertificates)) {
                        $this->Flash->success(__('The admission form setting has been added successfully.'));
                        return $this->redirect(['action' => 'index']);
                    }
                } else {
                    $this->Flash->error(implode('<br />', $err1), ['escape' => false]);
                }
            } else {
                $this->Flash->error(__('The admission form setting could not be added. Please add atleast one student'));
            }
        }

        $classes = $this->Tcertificates->Classes->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Classes.is_deleted' => false])
                ->order(['Classes.lft' => 'ASC']);

        $sessions = $this->Tcertificates->Sessions->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Sessions.is_deleted' => false])
                ->order(['Sessions.lft' => 'ASC']);

        $this->set(compact('tcertificates', 'classes', 'sessions'));
        $this->set('activeMenu', 'Admin.Tcertificates.index');
    }

    public function edit($id = null) {
        $this->set('page_title', __('Edit Admission Form Setting'));

        try {
            $tcertificates = $this->Tcertificates->get($id, [
                'contain' => [
                    'Classes' => [
                        'conditions' => ['Classes.is_deleted' => false]
                    ],
                    'Sessions' => [
                        'conditions' => ['Sessions.is_deleted' => false]
                    ]
                ],
                'conditions' => [
                    'Tcertificates.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid TC id selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        if($tcertificates)
        {
            $formattableFields = ['dob'];
            foreach($formattableFields as $formattableField)
            {
                if($tcertificates->{$formattableField})
                {
                    $fieldDate = new Time($tcertificates->{$formattableField});
                    $tcertificates->{$formattableField} = $fieldDate->format('d-m-Y');
                }
            }
        }
        if ($this->request->is(['patch', 'post', 'put'])) {
            
            $tcertificates2 = $this->request->withData('is_deleted', $tcertificates->is_deleted);
            $tcertificates = $this->Tcertificates->patchEntity($tcertificates, $tcertificates2->getData());

            if (!$tcertificates->errors()) {
                $errors = [];
                if ($this->request->getData('tc_image.tmp_name')) {
                    $fileName = strtolower(pathinfo($this->request->getData('tc_image.name'), PATHINFO_FILENAME));
                    $fileExtension = strtolower(pathinfo($this->request->getData('tc_image.name'), PATHINFO_EXTENSION));
                    $tcertificates->tc_image = static::FILE_PATH . $fileName . '.' . $fileExtension;

                    $file = new File(static::IMAGE_ROOT . $tcertificates->tc_image);
                    $directory = $file->folder();
                    $directory->create(static::IMAGE_ROOT . static::FILE_PATH);

                    $i = 1;
                    while ($file->exists()) {
                        $tcertificates->tc_image = static::FILE_PATH . $fileName . ' (' . $i++ . ').' . $fileExtension;
                        $file = new File(static::IMAGE_ROOT . $tcertificates->tc_image);
                    }

                    $success = move_uploaded_file($this->request->getData('tc_image.tmp_name'), static::IMAGE_ROOT . $tcertificates->tc_image);
                    if (!$success) {
                        $errors[] = __('Unable to upload TC. Please try again.');
                    }
                }
                if (empty($errors)) {
                    if ($this->Tcertificates->save($tcertificates)) {
                        $this->Flash->success(__('The TC has been updated successfully.'));
                        return $this->redirect($this->_redirectUrl());
                    }
                } else {
                    $this->Flash->error(implode('<br />', $errors), ['escape' => false]);
                }
            } else {

                $this->Flash->error(__('The TC could not be updated. Please see warning(s) below.'));
            }
        }

        $classes = $this->Tcertificates->Classes->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Classes.is_deleted' => false])
                ->order(['Classes.lft' => 'ASC']);

        $sessions = $this->Tcertificates->Sessions->find('list')
                ->select(['id', 'name', 'status'])
                ->where(['Sessions.is_deleted' => false])
                ->order(['Sessions.lft' => 'ASC']);
        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('tcertificates', 'classes', 'sessions', 'imageRoot','imagePath'));
        $this->set('activeMenu', 'Admin.Tcertificates.index');
    }

    public function delete($id = null) {
        try {
            $this->request->allowMethod(['post', 'delete']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $tcertificates = $this->Tcertificates->get($id, [
                'conditions' => [
                    'Tcertificates.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid gallery image selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $tcertificates->is_deleted = NULL;
        if ($this->Tcertificates->save($tcertificates)) {
            $this->Flash->success(__('The TC image has been deleted successfully.'));
        } else {
            $this->Flash->error(__('The TC image could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }

    public function statusChange($state = 'inactive', $id = null) {
        try {
            $this->request->allowMethod(['post']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $tcertificates = $this->Tcertificates->get($id, [
                'conditions' => [
                    'Tcertificates.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid TC image selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        if ($state == 'active') {
            $status = 'active';
            $tcertificates2 = $this->request->withData('status', 1);
        } else {
            $status = 'inactive';
            $tcertificates2 = $this->request->withData('status', 0);
        }

        $tcertificates = $this->Tcertificates->patchEntity($tcertificates, $tcertificates2->getData());
        if ($this->Tcertificates->save($tcertificates)) {
            $this->Flash->success(__('The TC image has been {0} successfully.', $status));
        } else {
            $this->Flash->error(__('The TC image could not be {0}. Please try again.', $status));
        }
        return $this->redirect($this->_redirectUrl());
    }

}
