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

class SessionsController extends AppController {

    public function initialize() {
        parent::initialize();

        if (!$this->userAuthorized()) {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }

    protected function _index() {
        $paramarters = [
            ['name', 'Sessions.name', 'LIKE', ''],
            ['status', 'Sessions.status', 'EQUALS', ''],
            ['created_from', 'Sessions.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Sessions.created', 'lessThanOrEqual', 'DATE']
        ];

        $filters = $this->Search->formFilters($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Sessions.is_deleted' => false
        ];

        return [$conditions];
    }

    public function index() {
        $this->set('page_title', __('List Sessions'));

        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'status', 'lft', 'created'],
            'conditions' => $conditions,
            'order' => ['Sessions.lft' => 'ASC'],
            'sortWhitelist' => ['Sessions.id', 'Sessions.name', 'Sessions.status', 'Sessions.lft', 'Sessions.created']
        ];

        try {
            $sessions= $this->paginate($this->Sessions);
        } catch (NotFoundException $e) {
            $totalRecord = $this->Sessions->find()
                    ->where($conditions)
                    ->count();

            $pageCount = ceil(($totalRecord ? $totalRecord : 1) / $this->request->getParam('paging.' . $this->modelClass . '.perPage'));
            if ($pageCount > 1) {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }

        $treeList = $this->Sessions->find('list', [
                    'valueField' => 'parent_id'
                ])->where($conditions)
                ->order(['parent_id' => 'ASC', 'lft' => 'ASC']);

        $this->set(compact('sessions', 'treeList'));
        $this->set('activeMenu', 'Admin.Sessions.index');

        if ($this->request->is('ajax')) {
            $this->viewBuilder()
                    ->setLayout('ajax')
                    ->setTemplatePath('Element' . DS . 'Admin' . DS . 'Sessions');
        }
    }

    public function add() {
        $this->set('page_title', __('Add New Session'));

        $session = $this->Sessions->newEntity();
        if ($this->request->is('post')) {
            $session2 = $this->request->withData('is_deleted', 0);
            $session = $this->Sessions->patchEntity($session, $session2->getData());
            if (!$session->errors()) {

                if ($this->Sessions->save($session)) {
                    if ($this->request->getData('top_on_list')) {
                        $this->Sessions->moveUp($session, true);
                    }

                    $this->Flash->success(__('The session has been added successfully.'));
                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->Flash->error(__('The session could not be added. Please see warning(s) below.'));
                }
            } else {
                $this->Flash->error(__('The session could not be added. Please see warning(s) below.'));
            }
        }

        $this->set(compact('session'));
        $this->set('activeMenu', 'Admin.Sessions.index');
    }

    public function edit($id = null) {
        $this->set('page_title', __('Edit Class'));

        try {
            $session = $this->Sessions->get($id, [
                'conditions' => [
                    'Sessions.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid session selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $session2 = $this->request->withData('is_deleted', $session->is_deleted);
            $session = $this->Sessions->patchEntity($session, $session2->getData());
            if (!$session->errors()) {
                if ($this->Sessions->save($session)) {
                    $this->Flash->success(__('The session has been updated successfully.'));
                    return $this->redirect($this->_redirectUrl());
                } else {
                    $this->Flash->error(__('The session could not be updated. Please see warning(s) below.'));
                }
            } else {
                $this->Flash->error(__('The session could not be updated. Please see warning(s) below.'));
            }
        }

        $this->set(compact('session'));
        $this->set('activeMenu', 'Admin.Sessions.index');
    }

    public function delete($id = null) {
        try {
            $this->request->allowMethod(['post', 'delete']);
        } catch (MethodNotAllowedException $e) {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }

        try {
            $session = $this->Sessions->get($id, [
                'conditions' => [
                    'Sessions.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid session selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        $session->is_deleted = NULL;
        if ($this->Sessions->save($session)) {
            $this->Flash->success(__('The session has been deleted successfully.'));
        } else {
            $this->Flash->error(__('The session could not be deleted. Please try again.'));
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
            $session = $this->Sessions->get($id, [
                'conditions' => [
                    'Sessions.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid session selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        if ($state == 'active') {
            $status = 'active';
            $session2 = $this->request->withData('status', 1);
        } else {
            $status = 'inactive';
            $session2 = $this->request->withData('status', 0);
        }

        $session = $this->Sessions->patchEntity($session, $session2->getData());
        if ($this->Sessions->save($session)) {
            $this->Flash->success(__('The session has been {0} successfully.', $status));
        } else {
            $this->Flash->error(__('The session could not be {0}. Please try again.', $status));
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
            $session = $this->Sessions->get($id, [
                'conditions' => [
                    'Sessions.is_deleted' => false
                ]
            ]);
        } catch (RecordNotFoundException $e) {
            $this->Flash->error(__('Invalid session selection.'));
            return $this->redirect($this->_redirectUrl());
        }

        list($conditions) = $this->_index();
        if ($direction == 'up') {
            $respectRecord = $this->Sessions->find()
                    ->select(['lft'])
                    ->where($conditions)
                    ->andWhere(['lft <' => $session->lft])
                    ->andWhere(['parent_id IS' => $session->parent_id])
                    ->order(['lft' => 'DESC'])
                    ->first();

            if ($respectRecord) {
                $moveCount = $this->Sessions->find()
                        ->where(['parent_id IS' => $session->parent_id])
                        ->andWhere(['lft <' => $session->lft, 'lft >=' => $respectRecord->lft])
                        ->count();

                if ($moveCount) {
                    $result = $this->Sessions->moveUp($session, $moveCount);
                }
            }
        } else {
            $respectRecord = $this->Sessions->find()
                    ->select(['lft'])
                    ->where($conditions)
                    ->andWhere(['lft >' => $session->lft])
                    ->andWhere(['parent_id IS' => $session->parent_id])
                    ->order(['lft' => 'ASC'])
                    ->first();

            if ($respectRecord) {
                $moveCount = $this->Sessions->find()
                        ->where(['parent_id IS' => $session->parent_id])
                        ->andWhere(['lft >' => $session->lft, 'lft <=' => $respectRecord->lft])
                        ->count();

                if ($moveCount) {
                    $result = $this->Sessions->moveDown($session, $moveCount);
                }
            }
        }

        if ($respectRecord && $moveCount && $result) {
            $this->Flash->success(__('The session has been moved {0} successfully.', $direction));
        } else {
            $this->Flash->error(__('The session could not be reordered. Please note, {0} move is not permitted for {1} child.', ucfirst($direction), (($direction == 'up') ? 'first' : 'last')));
        }
        return $this->redirect($this->_redirectUrl());
    }

}
