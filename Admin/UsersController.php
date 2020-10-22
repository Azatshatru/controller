<?php
/**
 * @author: Manoj Tanwar
 * @date: Apr 25, 2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Mailer\Email;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class UsersController extends AppController
{
    const SEARCH_PREFIX = 'MY_SEARCH';
    
    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('CakephpCaptcha.Captcha');
        $passed = ['forgotPassword', 'resetPassword', 'login', 'logout', 'changeProfile', 'changePassword'];
        if(!in_array($this->request->getParam('action'), $passed) && !$this->userAuthorized())
        {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
        
        $this->Auth->allow(['forgotPassword', 'resetPassword', 'logout','image']);
    }
    
    protected function _index()
    {
        $paramarters = [
            ['name', 'Users.name', 'LIKE', ''],
            ['email', 'Users.email', 'LIKE', ''],
            ['role', 'Users.role', 'EQUALS', ''],
            ['mobile', 'Users.mobile', 'LIKE', ''],
            ['last_login_from', 'Users.last_login', 'greaterThanOrEqual', 'DATE'],
            ['last_login_to', 'Users.last_login', 'lessThanOrEqual', 'DATE'],
            ['status', 'Users.status', 'EQUALS', ''],
            ['created_from', 'Users.created', 'greaterThanOrEqual', 'DATE'],
            ['created_to', 'Users.created', 'lessThanOrEqual', 'DATE']
        ];
        
        $filters = $this->Search->formFilters($this->modelClass.'.ADINDEX', $paramarters);
        
        $conditions = [
            $filters,
            'Users.is_deleted' => false
        ];
        
        if(!$this->Auth->user('is_system'))
        {
            $conditions['Users.is_system'] = false;
        }
        
        return [$conditions];
    }
    
    public function index()
    {
        $this->set('page_title', __('List Users'));
        
        list($conditions) = $this->_index();
        $this->paginate = [
            'fields' => ['id', 'name', 'email', 'role', 'mobile', 'status', 'last_login', 'created'],
            'conditions' => $conditions,
            'order' => ['Users.name' => 'ASC'],
            'sortWhitelist' => ['Users.id', 'Users.name', 'Users.email', 'Users.role', 'Users.mobile', 'Users.status', 'Users.last_login', 'Users.created']
        ];
        
        try
        {
            $users = $this->paginate($this->Users);
        }
        catch(NotFoundException $e)
        {
            $totalRecord = $this->Users->find()
                ->where($conditions)
                ->count();
            
            $pageCount = ceil(($totalRecord?$totalRecord:1)/$this->request->getParam('paging.'.$this->modelClass.'.perPage'));
            if($pageCount > 1)
            {
                return $this->redirect(['action' => 'index', 'page' => $pageCount]);
            }
            return $this->redirect(['action' => 'index']);
        }
        
        $this->set(compact('users'));
        $this->set('activeMenu', 'Admin.Users.index');
        
        if($this->request->is('ajax'))
        {
            $this->viewBuilder()
                ->setLayout('ajax')
                ->setTemplatePath('Element'.DS.'Admin'.DS.'Users');
        }
    }
    
    public function add()
    {
        $this->set('page_title', __('Add New User'));
        
        $user = $this->Users->newEntity();
        if($this->request->is('post'))
        {
            $user2 = $this->request->withData('is_deleted', 0);
            $user = $this->Users->patchEntity($user, $user2->getData());
            if($this->Users->save($user))
            {
                $this->Flash->success(__('The user has been added successfully.'));
                return $this->redirect(['action' => 'index']);
            }
            
            $this->Flash->error(__('The user could not be added. Please see warning(s) below.'));
        }
        
        $modules = $this->Users->Modules->find('list')
            ->where(['Modules.status' => true]);
        
        if($this->Auth->user('role') != 'Admin')
        {
            $modules->matching('Users', function($q){
                return $q->where(['Users.id' => $this->Auth->user('id')]);
            });
        }
        
        $this->set(compact('user', 'modules'));
        $this->set('activeMenu', 'Admin.Users.index');
    }
    
    public function edit($id = null)
    {
        $this->set('page_title', __('Edit User'));
        
        try
        {
            $conditions = [];
            if(!$this->Auth->user('is_system'))
            {
                $conditions['Users.is_system'] = false;
            }
            
            $user = $this->Users->get($id, [
                'fields' => ['id', 'name', 'email', 'role', 'mobile', 'is_system', 'status', 'is_deleted'],
                'contain' => ['Modules'],
                'conditions' => [
                    'Users.is_deleted' => false,
                    $conditions
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid user selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $user2 = $this->request->withData('is_deleted', $user->is_deleted);
            $user = $this->Users->patchEntity($user, $user2->getData());
            if($this->Users->save($user))
            {
                $this->Flash->success(__('The user has been updated successfully.'));
                return $this->redirect($this->_redirectUrl());
            }
            
            $this->Flash->error(__('The user could not be updated. Please see warning(s) below.'));
        }
        
        $modules = $this->Users->Modules->find('list')
            ->where(['Modules.status' => true]);
        
        if($this->Auth->user('role') != 'Admin')
        {
            $modules->matching('Users', function($q){
                return $q->where(['Users.id' => $this->Auth->user('id')]);
            });
        }
        
        $this->set(compact('user', 'modules'));
        $this->set('activeMenu', 'Admin.Users.index');
    }
    
    public function delete($id = null)
    {
        try
        {
            $this->request->allowMethod(['post', 'delete']);
        }
        catch(MethodNotAllowedException $e)
        {
            $this->Flash->error(__('Requested action is not permitted.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        try
        {
            $conditions = [];
            if(!$this->Auth->user('is_system'))
            {
                $conditions['Users.is_system'] = false;
            }
            
            $user = $this->Users->get($id, [
                'conditions' => [
                    'Users.is_deleted' => false,
                    $conditions
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid user selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($user->is_system)
        {
            $users = $this->Users->find()
                ->where(['Users.is_deleted' => false, 'Users.is_system' => true])
                ->andWhere(['OR' => ['Users.status' => true, 'Users.id' => $id]]);
            
            if($users->count() < 2)
            {
                $this->Flash->error(__('Deletion of this user is not permitted.'));
                return $this->redirect($this->_redirectUrl());
            }
        }
        
        $user->is_deleted = NULL;
        if($this->Users->save($user))
        {
            $this->Flash->success(__('The user has been deleted successfully.'));
        }
        else
        {
            $this->Flash->error(__('The user could not be deleted. Please try again.'));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    public function statusChange($state = 'inactive', $id = null)
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
        
        try
        {
            $conditions = [];
            if(!$this->Auth->user('is_system'))
            {
                $conditions['Users.is_system'] = false;
            }
            
            $user = $this->Users->get($id, [
                'conditions' => [
                    'Users.is_deleted' => false,
                    $conditions
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid user selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($state == 'active')
        {
            $status = 'active';
            $user2 = $this->request->withData('status', 1);
        }
        else
        {
            if($user->is_system)
            {
                $users = $this->Users->find()
                    ->where(['Users.is_deleted' => false, 'Users.is_system' => true, 'Users.status' => true])
                    ->andWhere(['Users.id !=' => $id]);
                
                if($users->count() < 2)
                {
                    $this->Flash->error(__('Inactivation of this user is not permitted.'));
                    return $this->redirect($this->_redirectUrl());
                }
            }
            
            $status = 'inactive';
            $user2 = $this->request->withData('status', 0);
        }
        
        $user = $this->Users->patchEntity($user, $user2->getData());
        if($this->Users->save($user))
        {
            $this->Flash->success(__('The user has been {0} successfully.', $status));
        }
        else
        {
            $this->Flash->error(__('The user could not be {0}. Please try again.', $status));
        }
        return $this->redirect($this->_redirectUrl());
    }
    
    public function changeProfile()
    {
        $this->set('page_title', 'My Profile');
        
        try
        {
            $user = $this->Users->get($this->Auth->user('id'), [
                'fields' => ['id', 'name', 'email', 'mobile', 'status', 'is_deleted'],
                'conditions' => [
                    'Users.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid access found.'));
            
            $this->_sessionDestroy();
            return $this->redirect($this->Auth->logout());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $user2 = $this->request->withData('is_deleted', $user->is_deleted);
            $user = $this->Users->patchEntity($user, $user2->getData());
            if($this->Users->save($user))
            {
                $this->Flash->success(__('Profile has been updated successfully.'));
                return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
            }
            
            $this->Flash->error(__('Profile could not be updated. Please see warning(s) below.'));
        }
        
        $this->set(compact('user'));
        $this->set('activeMenu', 'Admin.Users.changeProfile');
    }
    
    public function changePassword()
    {
        $this->set('page_title', 'Change Password');
        
        try
        {
            $user = $this->Users->get($this->Auth->user('id'), [
                'fields' => ['id', 'name', 'email', 'mobile', 'status', 'is_deleted'],
                'conditions' => [
                    'Users.is_deleted' => false
                ]
            ]);
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid access found.'));
            
            $this->_sessionDestroy();
            return $this->redirect($this->Auth->logout());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
            $user2 = $this->request->withData('is_deleted', $user->is_deleted);
            $user = $this->Users->patchEntity($user, $user2->getData());
            if($this->Users->save($user))
            {
                $this->Flash->success(__('Password has been updated successfully.'));
                return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
            }
            
            $this->Flash->error(__('Password could not be updated. Please see warning(s) below.'));
        }
        
        $this->set(compact('user'));
        $this->set('activeMenu', 'Admin.Users.changePassword');
    }
    
    public function login()
    {
        $this->set('page_title', 'Administration Login');
        $this->viewBuilder()->layout('admin_login');
        
        if($this->request->is('post'))
        {
			$isHuman = $this->Captcha->check($this->request->data['CaptchaCode']);
            unset($this->request->data['CaptchaCode']);
            if($isHuman){
            $this->_sessionDestroy();
            $this->Auth->logout();
            
            $user = $this->Auth->identify();
            if($user && $user['is_deleted'] === false)
            {
                if($user['status'])
                {
                    $this->Auth->setUser($user);
                    
                    $currentUser = $this->Users->get($user['id']);
                    if($currentUser)
                    {
                        $this->Users->touch($currentUser, 'Users.login');
                        $this->Users->save($currentUser);
                    }
                    
                    $this->request->session()->write('NTS_KCFINDER.disabled', false);
                    return $this->redirect($this->Auth->redirectUrl());
                }
                else
                {
                    $this->Flash->error(__('Your account is suspended. Please contact site administrator.'));
                    return $this->redirect($this->Auth->loginAction);
                }
            }
            }else{
                $this->Flash->error(__('Captcha validation failed'));
            return $this->redirect($this->Auth->loginAction);
            }
            $this->Flash->error(__('Invalid username or password, try again'));
            return $this->redirect($this->Auth->loginAction);
        }
        
        $user = $this->Users->newEntity();
        $this->set(compact('user'));
    }
    
    public function logout()
    {
        $this->_sessionDestroy();
        return $this->redirect($this->Auth->logout());
    }
    
    public function forgotPassword()
    {
        $this->set('page_title', 'Forgot password');
        $this->viewBuilder()->layout('admin_login');
        
        $user = $this->Users->newEntity();
        if($this->request->is(['patch', 'post', 'put']))
        {
            $user = $this->Users->patchEntity($user, $this->request->getData(), [
                'validate' => 'forgotPassword'
            ]);
            if(!$user->errors())
            {
                $userInfo = $this->Users->find()
                    ->select(['id', 'name', 'email', 'status', 'is_deleted'])
                    ->where(['Users.email' => $this->request->getData('email')])
                    ->andWhere(['Users.is_deleted' => false])
                    ->first();
                
                if($userInfo)
                {
                    if($userInfo->status)
                    {
                        $time = new Time();
                        $time->modify('+3 Days');
                        
                        $user2 = $this->request
                            ->withData('password_token', $this->_getRandomString(6).'-'.$this->_getRandomString(6).'-'.
                                crypt($userInfo->id, 'IFWCMS').'-'.$this->_getRandomString(6).'-'.$this->_getRandomString(6))
                            ->withData('token_expiry', $time->format('Y-m-d H:i:s'))
                            ->withData('is_deleted', $userInfo->is_deleted);
                        
                        $user = $this->Users->patchEntity($userInfo, $user2->getData(), [
                            'accessibleFields' => ['password_token' => true, 'token_expiry' => true]
                        ]);
                        
                        if($this->Users->save($user))
                        {
                            $email = new Email('default');
                            $email->emailFormat('html')
                                ->setFrom($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                                ->replyTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                                ->setTo($userInfo->email, $userInfo->name)
                                ->setSubject('Reset your Password for '.$this->coreVariable['siteName'])
                                ->template('forgot_password')
                                ->viewVars([
                                    'userInfo' => $userInfo,
                                    'passwordToken' => $user->password_token,
                                    'tokenExpiry' => $user->token_expiry,
                                    'sitename' => $this->coreVariable['siteName']
                                ])
                                ->send();
                            
                            $this->Flash->success(__('An email have been sent to your email address with instructions to reset your password.'));
                            return $this->redirect(['action' => 'login']);
                        }
                        else
                        {
                            $this->Flash->error(__('We are unable to complete your request at this time. Please try again.'));
                        }
                    }
                    else
                    {
                        $this->Flash->error(__('Your account is suspended. Please contact site administrator.'));
                    }
                }
                else
                {
                    $this->Flash->error(__('We couldn\'t find an account named with {0} as email address.', $this->request->getData('email')));
                }
            }
            else
            {
                $this->Flash->error(__('Please correct the error(s) below and try again.'));
            }
        }
        
        $this->set(compact('user'));
    }
    
    public function resetPassword($passwordToken = null)
    {
        $this->set('page_title', 'Reset password');
        $this->viewBuilder()->layout('admin_login');
        
        $userInfo = $this->Users->find()
            ->select(['id', 'name', 'email', 'status', 'token_expiry', 'is_deleted'])
            ->where([
                'Users.password_token' => $passwordToken,
                'Users.is_deleted' => false
            ])
            ->first();
        
        if($userInfo)
        {
            if($userInfo->status)
            {
                $timeCurrent = new Time();
                $timeExpiry = new Time($userInfo->token_expiry);
                if($timeExpiry->format('Y-m-d H:i:s') >= $timeCurrent->format('Y-m-d H:i:s'))
                {
                    $user = $this->Users->newEntity();
                    if($this->request->is(['patch', 'post', 'put']))
                    {
                        $user2 = $this->request
                            ->withData('password_token', '')
                            ->withData('token_expiry', NULL)
                            ->withData('is_deleted', $userInfo->is_deleted);
                        
                        $user = $this->Users->patchEntity($userInfo, $user2->getData(), [
                            'validate' => 'resetPassword',
                            'accessibleFields' => ['password_token' => true, 'token_expiry' => true]
                        ]);
                        
                        if($this->Users->save($user))
                        {
                            $email = new Email('default');
                            $email->emailFormat('html')
                                ->setFrom($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                                ->replyTo($this->coreVariable['emailSenderEmail'], $this->coreVariable['emailSenderName'])
                                ->setTo($userInfo->email, $userInfo->name)
                                ->setSubject($this->coreVariable['siteName'].' account password has been changed successfully')
                                ->template('reset_password')
                                ->viewVars([
                                    'userInfo' => $userInfo,
                                    'sitename' => $this->coreVariable['siteName']
                                ])
                                ->send();
                            
                            $this->Flash->success(__('Your password has been reset successfully! Please login using your email address and new password.'));
                            return $this->redirect(['action' => 'login']);
                        }
                        else
                        {
                            $this->Flash->error(__('We are unable to complete your request at this time. Please try again.'));
                        }
                    }
                }
                else
                {
                    $this->Flash->error(__('Your password reset token has been expired.'));
                    return $this->redirect(['action' => 'forgotPassword']);
                }
            }
            else
            {
                $this->Flash->error(__('Your account is suspended. Please contact site administrator.'));
                return $this->redirect(['action' => 'login']);
            }
        }
        else
        {
            $this->Flash->error(__('Token supplied is invalid.'));
            return $this->redirect(['action' => 'forgotPassword']);
        }
        
        $this->set(compact('user'));
    }
    
    protected function _sessionDestroy()
    {
        $this->request->session()->delete('NTS_KCFINDER.disabled');
        $this->request->session()->delete(static::SEARCH_PREFIX);
    }
	public function image() {
        $this->autoRender = false;
        echo $this->Captcha->image(5); 
        exit;
    }
}
