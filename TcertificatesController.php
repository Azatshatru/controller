<?php
/**
 * @author: Manoj Tanwar
 * @date: April 22,2019
 * @version: 1.0.0
 */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Filesystem\File;
use Cake\Network\Exception\NotFoundException;

class TcertificatesController extends AppController {

    const FILE_PATH = 'tcertificates' . DS;

    public function initialize() {
        parent::initialize();
        $this->Auth->allow(['index']);
    }

    protected function _index() {
        $paramarters = [
            ['scholar_no', 'Tcertificates.scholar_no', 'LIKE', ''],
            ['dob', 'Tcertificates.dob', 'EQUALS', 'DATE']
        ];

        $filters = $this->Search->formFiltersFront($this->modelClass . '.ADINDEX', $paramarters);

        $conditions = [
            $filters,
            'Tcertificates.is_deleted' => false,
            'Sessions.is_deleted' => false,
            'Classes.is_deleted' => false,
        ];
		 $contain = [
            'Sessions' => [
                'fields' => ['id', 'name', 'status']
            ],
            'Classes' => [
                'fields' => ['id', 'name', 'status']
            ]
        ];
        return [$conditions, $contain];
    }

    public function index() {
        $this->set('page_title', __('Transfer Certificates'));
        $tcertificates = array();
        list($conditions, $contain) = $this->_index();

        if ($this->request->is('post') || !empty($conditions[0])) {
       
            try {
                $tcertificates = $this->Tcertificates->find()
                        ->select(['id', 'scholar_no','student_name', 'session_id', 'dob', 'tc_image', 'status'])
                        ->contain($contain)
                        ->where($conditions)
                        ->first();
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
            
            if ($this->request->is('ajax')) {
                $this->viewBuilder()
                        ->setLayout('ajax')
                        ->setTemplatePath('Element' . DS . 'Tcertificates' . DS . 'search');
            }
        }
        $imagePath = static::IMAGE_ROOT;
        $imageRoot = static::IMAGE_DIR;
        $this->set(compact('tcertificates','imageRoot','imagePath'));
    }
    

}
