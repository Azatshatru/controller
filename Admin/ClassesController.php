<?php

/**
 * @author: Sonia Solanki
 * @date: Oct 15,2018
 * @version: 1.0.0
 */

namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Filesystem\File;
use Cake\I18n\Time;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class ClassesController extends AppController {

    public function initialize() {
        parent::initialize();

        if (!$this->userAuthorized()) {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }

    protected function _index() {
        $paramarters = [
            ['name', 'Classes.name', 'LIKE', ''],
            ['status', 'Classes.status', 'EQUALS', ''],
            ['created_from', 'Classes.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Classes.created', 'lessThanOrEqual', 'DATE']
        ];

        $filters = $this->Search->formFilters($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Classes.is_deleted' => false
        ];

        return [$conditions];
    }

    public function index() {
        $this->set('page_title', __('List Classes'));

        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['Classes.lft' => 'ASC'],
            'sortWhitelist' => ['Classes.id', 'Classes.name', 'Classes.status', 'Classes.lft', 'Classes.created']
        ];

        try {
            $classes= $this->paginate($this->Classes);
        } catch (NotFoundException $e) {
            $totalRecord = $this->Classes->find()
                    ->where($conditions)
                    ->count();

            $pageCount = ceil(($totalRecord ? $totalRecord : 1) / $this->request->getParam('paging.' . $this->modelClass . '.perPage'));
            if ($pageCount > 1) {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }

        $treeList = $this->Classes->find('list', [
                    'valueField' => 'parent_id'
                ])->where($conditions)
                ->order(['parent_id' => 'ASC', 'lft' => 'ASC']);

        $this->set(compact('classes', 'treeList'));
        $this->set('activeMenu', 'Admin.Classes.index');

        if ($this->request->is('ajax')) {
            $this->viewBuilder()
                    ->setLayout('ajax')
                    ->setTemplatePath('Element' . DS . 'Admin' . DS . 'Classes');
        }
    }

    public function add() {
        $this->set('page_title', __('Add New Class'));

        $class = $this->Classes->newEntity();
        if ($this->request->is('post')) {
            $class2 = $this->request->withData('is_deleted', 0);
            $class = $this->Classes->patchEntity($class, $class2->getData());
            if (!$class->errors()) {

                if ($this->Classes->save($class)) {
                    if ($this->request->getData('top_on_list')) {
                        $this->Classes->moveUp($class, true);
                    }

                    $this->Flash->success(__('The class has been added successfully.'));
                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->Flash->error(__('The class could not be added. Please see warning(s) below.'));
                }
            } else {
                $this->Flash->error(__('The class could not be added. Please see warning(s) below.'));
            }
        }

        $this->set(compact('class'));
        $this->set('activeMenu', 'Admin.Classes.index');
    }

    public function edit($id = null) {
        $this->set('page_title', __('Edit Class'));

        try {
            $class = $this->Classes->get($id, [
                'conditions' => [
                    'Classes.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid class selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $class2 = $this->request->withData('is_deleted', $class->is_deleted);
            $class = $this->Classes->patchEntity($class, $class2->getData());
            if (!$class->errors()) {
                if ($this->Classes->save($class)) {
                    $this->Flash->success(__('The class has been updated successfully.'));
                    return $this->redirect($this->_redirectUrl());
                } else {
                    $this->Flash->error(__('The class could not be updated. Please see warning(s) below.'));
                }
            } else {
                $this->Flash->error(__('The class could not be updated. Please see warning(s) below.'));
            }
        }

        $this->set(compact('class'));
        $this->set('activeMenu', 'Admin.Classes.index');
    }

    public function delete($id = null) {
        try {
            $this->request->allowMethod(['post', 'delete']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $class = $this->Classes->get($id, [
                'conditions' => [
                    'Classes.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid class selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $class->is_deleted = NULL;
        if ($this->Classes->save($class)) {
            $this->Flash->success(__('The class has been deleted successfully.'));
        } else {
            $this->Flash->error(__('The class could not be deleted. Please try again.'));
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
            $class = $this->Classes->get($id, [
                'conditions' => [
                    'Classes.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid class selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        if ($state == 'active') {
            $status = 'active';
            $class2 = $this->request->withData('status', 1);
        } else {
            $status = 'inactive';
            $class2 = $this->request->withData('status', 0);
        }

        $class = $this->Classes->patchEntity($class, $class2->getData());
        if ($this->Classes->save($class)) {
            $this->Flash->success(__('The class has been {0} successfully.', $status));
        } else {
            $this->Flash->error(__('The class could not be {0}. Please try again.', $status));
        }
        return $this->redirect($this->_redirectUrl());
    }

    public function move($direction = 'down', $id = null) {
        try {
            $this->request->allowMethod(['post']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $class = $this->Classes->get($id, [
                'conditions' => [
                    'Classes.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid class selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        list($conditions) = $this->_index();
        if ($direction == 'up') {
            $respectRecord = $this->Classes->find()
                    ->select(['lft'])
                    ->where($conditions)
                    ->andWhere(['lft <' => $class->lft])
                    ->andWhere(['parent_id IS' => $class->parent_id])
                    ->order(['lft' => 'DESC'])
                    ->first();

            if ($respectRecord) {
                $moveCount = $this->Classes->find()
                        ->where(['parent_id IS' => $class->parent_id])
                        ->andWhere(['lft <' => $class->lft, 'lft >=' => $respectRecord->lft])
                        ->count();

                if ($moveCount) {
                    $result = $this->Classes->moveUp($class, $moveCount);
                }
            }
        } else {
            $respectRecord = $this->Classes->find()
                    ->select(['lft'])
                    ->where($conditions)
                    ->andWhere(['lft >' => $class->lft])
                    ->andWhere(['parent_id IS' => $class->parent_id])
                    ->order(['lft' => 'ASC'])
                    ->first();

            if ($respectRecord) {
                $moveCount = $this->Classes->find()
                        ->where(['parent_id IS' => $class->parent_id])
                        ->andWhere(['lft >' => $class->lft, 'lft <=' => $respectRecord->lft])
                        ->count();

                if ($moveCount) {
                    $result = $this->Classes->moveDown($class, $moveCount);
                }
            }
        }

        if ($respectRecord && $moveCount && $result) {
            $this->Flash->success(__('The class has been moved {0} successfully.', $direction));
        } else {
            $this->Flash->error(__('The class could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up') ? 'first' : 'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }

}
