<?php
/**
 * @author: Manoj Tanwar
 * @date: April 03,2019
 * @version: 1.0.0
 */
namespace App\Controller\Admin;

use App\Controller\AppController;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\MethodNotAllowedException;
use Cake\Network\Exception\NotFoundException;

class SettingsController extends AppController
{
    public function initialize()
    {
        parent::initialize();
        if(!$this->userAuthorized())
        {
            $this->Flash->error(__('You are not authorized to access that location.'));
            return $this->redirect(['controller' => 'Dashboards', 'action' => 'index']);
        }
    }
    
    
    public function index()
    {
        $id = 1;
        $this->set('page_title', __('Edit Setting'));
        
        try
        {
            $setting = $this->Settings->get($id);
            
        }
        catch(RecordNotFoundException $e)
        {
            $this->Flash->error(__('Invalid setting selection.'));
            return $this->redirect($this->_redirectUrl());
        }
        
        if($this->request->is(['patch', 'post', 'put']))
        {
           
            $setting = $this->Settings->patchEntity($setting, $this->request->getData());
            if($this->Settings->save($setting))
            {   
                $this->Flash->success(__('The setting has been updated successfully.'));
                return $this->redirect(['action' => 'index']);
            }else{
            
            $this->Flash->error(__('The setting could not be updated. Please see warning(s) below.'));
            }
        }
        
        $this->set(compact('setting'));
        $this->set('activeMenu', 'Admin.Settings.index');
    }
}
