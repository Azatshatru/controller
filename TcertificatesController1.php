<?php

/**
 * @author: Sonia Solanki
 * @date: Oct 15,2018
 * @version: 1.0.0
 */

namespace App\Controller;

use App\Controller\AppController;
use Cake\Filesystem\File;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;
use Cake\Utility\Hash;

class TcertificatesController extends AppController {

    const FILE_PATH = 'tc' . DS;

    public function initialize() {
        parent::initialize();

        $this->Auth->allow(['index']);
    }


    public function index() { 
        $this->set('page_title', __(' Transfer Certificates'));

        try {
            
            
        } catch (NotFoundException $e) {
            return $this->redirect(['action' => 'index']);
        }
        $tcertificates = $this->Tcertificates->find()
                    ->select(['id', 'student_name', 'class_id', 'tc_image', 'session_id', 'created', 'status'])
                    ->contain([
                        'Classes' => [
                            'fields' => ['Classes.name','Classes.id'],
                            'conditions' => ['Classes.is_deleted' => false,'Classes.status' => 1]
                            ],
                        'Sessions' => [
                            'fields' => ['Sessions.name','Sessions.id'],
                            'conditions' => ['Sessions.is_deleted' => false,'Sessions.status' => 1]
                            ]
                        ])
                    ->where(['Tcertificates.is_deleted' => false,'Tcertificates.status' => 1])
                    ->order(['Sessions.lft' => 'ASC','Classes.lft' => 'ASC']);
        $tcertificates = $tcertificates->toArray();
        $class_id = Hash::combine($tcertificates,'{n}.class_id','{n}.class.name');
        $classes = array_unique($class_id);
        $i=0;
        $tc_arr = array();
        foreach($tcertificates as $tc){
            $key = $tc['session']['name'];
            $tc_arr[$key][$classes[$tc['class_id']]][$i]['student_name'] = $tc['student_name'];
            $tc_arr[$key][$classes[$tc['class_id']]][$i]['tc_image'] = $tc['tc_image'];
            $i++;
        }
        
        $imageRoot = static::IMAGE_ROOT;
        $imagePath = static::IMAGE_DIR;
        $this->set(compact('tc_arr', 'imageRoot','imagePath'));
        
    }

}
