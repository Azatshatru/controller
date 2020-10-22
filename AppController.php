<?php
/**
 * @author: Manoj Tanwar
 * @date: April 25,2019
 * @version: 1.0.0
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Mailer\Email;
use Muffin\Footprint\Auth\FootprintAwareTrait;

class AppController extends Controller
{
    use FootprintAwareTrait;
    const IMAGE_ROOT = WWW_ROOT.'img'.DS.'media'.DS;
    const IMAGE_DIR = 'img'.DS.'media'.DS;
    
    public function initialize()
    {
        parent::initialize();
       
        $this->loadComponent('RequestHandler', [
            'viewClassMap' => [
                'xlsx' => 'Cewi/Excel.Excel'
            ]
        ]);
        $this->loadComponent('Flash');
        $this->loadComponent('Search');
		$this->loadComponent('Common');
        $this->loadComponent('Auth', [
            'authenticate' => ['Form' => ['userModel' => $this->_userModel, 'fields' => ['username' => 'email']]],
            'loginAction' => ['controller' => 'Users', 'action' => 'login'],
            'loginRedirect' => ['controller' => 'Dashboards', 'action' => 'index'],
            'unauthorizedRedirect' => $this->referer()
        ]);
        
        if($this->request->getParam('prefix') == 'admin')
        {
            if(!$this->request->is('ajax'))
            {
                $this->viewBuilder()->layout('admin');
            }
            
            $this->Auth->config([
                'storage' => ['className' => 'Session', 'key' => 'Auth.Admin'
            ]]);
        }
        else
        {
			$result        = $this->Common->common();
			
			$settings     = $result['settings'];
			$imageRoot = static::IMAGE_ROOT;
			$this->set(compact('imageRoot','settings'));
			 // common elements end code
           Email::configTransport('gmail',[
                'className' => 'Smtp',
                'host' => @$settings->smtp_host,
                'port' => $settings->smtp_port,
                'tls' => $settings->tls,
                'username' => $settings->smtp_email,
                'password' => $settings->smtp_password,
				'context' => [
					'ssl' => [
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					]
				]
            ]);
            $this->coreVariable = [
                'emailFromName' => $settings->from_name,
                'emailFromEmail' => $settings->from_email,
                'emailSenderName' => $settings->sender_name,
                'emailSenderEmail' => $settings->send_to,
				'AdmissionSenderEmail' => $settings->admission_send_to,
                'CareerSenderEmail' => $settings->career_send_to,
                'siteName' => 'Etasha',
                'DevelopedBy' => 'IFW Web Studio',
            ];
		}
		    $this->userAuth = $this->Auth->user();
			$this->userId = $this->Auth->user('id');
			
    }
    
    public function beforeRender(Event $event)
    {
        parent::beforeRender($event);
        
        $userAuth = $this->userAuth;
        $coreVariable = $this->coreVariable;
        
        $this->loadModel('Modules');
        $navModules = $this->Modules->find('list')
            ->matching('Users', function($q){
                return $q->where(['Users.id' => $this->userId]);
            })
            ->toArray();
        $web_url = 'http://etasha.ifwwebstudio.com';
        $this->set(compact('coreVariable', 'userAuth', 'navModules','web_url'));
    }
    
    protected function _getRandomString($length = 10, $validCharacters = null)
    {
        if($validCharacters == '')
        {
            $validCharacters = '123456789ABCDEFGHIJKLMNPQRSTUVWXYZ';
        }
        
        $validCharactersCount = strlen($validCharacters);
        
        $string = '';
        for($i=0; $i<$length; $i++)
        {
            $string .= $validCharacters[mt_rand(0, $validCharactersCount-1)];
        }
        
        return $string;
    }
    
    protected function _redirectUrl()
    {
        if($this->request->query('page') > 1)
        {
            return ['action' => 'index', 'page' => $this->request->query('page')];
        }
        
        return ['action' => 'index'];
    }
    
    public function resetFilters()
    {
        try
        {
            $this->request->allowMethod(['post']);
        }
        catch(MethodNotAllowedException $e)
        {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        $this->Search->resetFilters($this->modelClass.'.ADINDEX');
        
        $this->Flash->success(__('Search filters are reset successfully.'));
        return $this->redirect($this->referer());
    }
    
    protected function userAuthorized()
    {
        if($this->Auth->user() && $this->Auth->user('role') != 'Admin')
        {
            $this->loadModel('Users');
            return $this->Users->find()
                ->where(['Users.id' => $this->Auth->user('id')])
                ->matching('Modules', function($q){
                    return $q->where(['Modules.name' => $this->request->getParam('controller')]);
                })->count();
        }
        
        return true;
    }
}
