<?php
/**
 * @author: Manoj Tanwar
 * @date: Apr 24, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Utility\Hash;

class DashboardsController extends AppController
{
    public function index()
    {
        $this->set('page_title', 'Dashboard');
		// Transfer certificates count
        
		// Downloads
        $this->loadModel('Downloads');
        $downloads = $this->Downloads->find()
                ->select(['id','status'])
                ->where(['Downloads.is_deleted' => false])
                ->toArray();
        $active = 0;
        $inactive = 0;
        foreach($downloads as $download){
            if($download['status']==1){
                $active++;
            }
            if($download['status']==0){
                $inactive++;
            }
        }
        $data['downloads']['active'] = $active;
        $data['downloads']['inactive'] = $inactive;
        $data['downloads']['total'] = sizeof($downloads);
		
		 // News count
        $this->loadModel('News');
        $news = $this->News->find()
                ->select(['id','status'])
                ->where(['News.is_deleted' => false])
                ->toArray();
        $active = 0;
        $inactive = 0;
        foreach($news as $news_l){
            if($news_l['status']==1){
                $active++;
            }
            if($news_l['status']==0){
                $inactive++;
            }
        }
        $data['news']['active'] = $active;
        $data['news']['inactive'] = $inactive;
        $data['news']['total'] = sizeof($news);
		
        // Enquiry count
        $this->loadModel('Enquiries');
        $enquiries = $this->Enquiries->find()
                ->select(['id','open'])
                ->where(['Enquiries.is_deleted' => false])
                ->toArray();
        $open = 0;
        $close = 0;
        foreach($enquiries as $enquiry){
            if($enquiry['open']==1){
                $open++;
            }
            if($enquiry['open']==0){
                $close++;
            }
        }
        $data['enquiries']['open'] = $open;
        $data['enquiries']['close'] = $close;
        $data['enquiries']['total'] = sizeof($enquiries);
        
		// Contact Enquiry count
        $this->loadModel('Contacts');
        $contacts = $this->Contacts->find()
                ->select(['id'])
                ->where(['Contacts.is_deleted' => false])
                ->toArray();
       
        $data['cenquiries']['total'] = sizeof($contacts);
		
        // Job Applicants count
        $this->loadModel('JobApplicants');
        $job_applicants = $this->JobApplicants->find()
                ->select(['id','view'])
				->contain(['Jobs'])
                ->where(['JobApplicants.is_deleted' => false,'Jobs.is_deleted'=>false])
                ->toArray();
        $open = 0;
        $close = 0;
        foreach($job_applicants as $job_applicant){
            if($job_applicant['view']==1){
                $open++;
            }
            if($job_applicant['view']==0){
                $close++;
            }
        }
        $data['job_applicants']['open'] = $open;
        $data['job_applicants']['close'] = $close;
        $data['job_applicants']['total'] = sizeof($job_applicants);
        
        $this->set(compact('data'));
        $this->set('activeMenu', 'Admin.Dashboards.index');
    }
    
}
