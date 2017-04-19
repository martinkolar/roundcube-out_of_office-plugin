<?php

/**
 * Out of Office
 *
 * This plugin adds an option of actiavting Out of Office message 
 * into the RoundCube. Adds Out of Office tab in Settings.
 *
 * @version 1.0
 * @author Martin Kolar <kolar@m2system.cz>
 *
 * Configuration (see config.inc.php.dist)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 */

define('OUTOFOFFICE_CONFIG_ERROR', 1);
define('OUTOFOFFICE_ERROR', 2);
define('OUTOFOFFICE_CONNECT_ERROR', 3);
define('OUTOFOFFICE_SUCCESS', 0);

class out_of_office extends rcube_plugin
{
    public $task = 'settings';
    public $noframe = true;
    public $noajax  = true;

    private $storage;
    private $user_values;
    
    function init()
    {
        $rcmail = rcmail::get_instance();
        $this->load_config();
        $this->add_texts('localization/');

        $this->add_hook('settings_actions', array($this, 'settings_actions'));
    
        
        // register actions
        $this->register_action('plugin.out_of_office', array($this, 'out_of_office_init'));
        $this->register_action('plugin.out_of_office-save', array($this, 'out_of_office_save'));
    }
    
    function settings_actions($args)
    {
        // register as settings action
        $args['actions'][] = array(
            'action' => 'plugin.out_of_office',
            'class'  => 'out_of_office',
            'label'  => 'out_of_office.out_of_office',
            'title'  => 'setooomessage',
            'domain' => 'out_of_office.out_of_office',
        );

        return $args;
    }    

    /**
     * Plugin action handler
     */
    function out_of_office_init()
    {
        $this->register_handler('plugin.body', array($this, 'out_of_office_form'));

        $this->_init_storage();
        $this->_load();
        $rcmail = rcmail::get_instance();
        $rcmail->output->set_pagetitle($this->gettext('out_of_office'));

        $rcmail->output->send('plugin');
    }

    /**
     * Forms save action handler
     */
    function out_of_office_save()
    {
        $this->register_handler('plugin.body', array($this, 'out_of_office_form'));

        $rcmail = rcmail::get_instance();
        $rcmail->output->set_pagetitle($this->gettext('out_of_office'));

        $oooactivate = isset($_POST['_oooactivate']) ? true : false;
        $ooosubject = rcube_utils::get_input_value('_ooosubject', rcube_utils::INPUT_POST, false);        
        $ooobody = rcube_utils::get_input_value('_ooobody', rcube_utils::INPUT_POST, true);        
rcube::write_log('errors', ":".$ooosubject);

        $this->_save($oooactivate, $ooosubject, $ooobody);

        $this->_load();

        $rcmail->output->send('plugin');
    }

    function out_of_office_form()
    {
        $rcmail = rcmail::get_instance();

        $rcmail->output->add_label(
            'out_of_office.noooosubject',
            'out_of_office.noooobody'
        );

        $table = new html_table(array('cols' => 2));
        // show OOO activate check-box
        $field_id = 'oooactivate';
        $checkbox_oooactivate = new html_checkbox(array(
                'name'         => '_oooactivate',
                'id'           => $field_id,
        ));

        $table->add('title', html::label($field_id, rcube::Q($this->gettext('oooactivate'))));
        $table->add(null, $checkbox_oooactivate->show(!$this->user_values['activate']));

        // show OOO Subject field
        $field_id = 'ooosubject';
        $text_ooosubject = new html_inputfield(array(
                'name'         => '_ooosubject',
                'id'           => $field_id,
                'size'         => 82,
                'autocomplete' => 'off', 
                'value'        => $this->user_values['subject'], 
        ));

        $table->add('title', html::label($field_id, rcube::Q($this->gettext('ooosubject'))));
        $table->add(null, $text_ooosubject->show());

        // show OOO Body field
        $field_id = 'ooobody';
        $text_ooobody = new html_textarea(array(
                'name'         => '_ooobody',
                'id'           => $field_id,
                'rows'         => 20,
                'cols'         => 80,
                'wrap'         => 'soft',
                'value'        => $this->user_values['body'], 
        ));

        $table->add('title', html::label($field_id, rcube::Q($this->gettext('ooobody'))));
        $table->add(null, $text_ooobody->show());

        $submit_button = $rcmail->output->button(array(
                'command' => 'plugin.out_of_office-save',
                'type'    => 'input',
                'class'   => 'button mainaction',
                'label'   => 'save',
        ));

        $out = html::div(array('class' => 'box'),
            html::div(array('id' => 'prefs-title', 'class' => 'boxtitle'), $this->gettext('out_of_office'))
            . html::div(array('class' => 'boxcontent'),
                $table->show() . html::p(null, $submit_button)));

        $rcmail->output->add_gui_object('out_of_office_form', 'out_of_office-form');

        $this->include_script('out_of_office.js');

        return $rcmail->output->form_tag(array(
            'id'     => 'out_of_office-form',
            'name'   => 'out_of_office-form',
            'method' => 'post',
            'action' => './?_task=settings&_action=plugin.out_of_office-save',
        ), $out);
    }

    private function _init_storage()
    {
        if(!$this->storage)
        {
            $config = rcmail::get_instance()->config;
            $driver = $config->get('outofoffice_driver', 'sql');
            $class = "rcube_out_of_office_" . $driver;
            $file   = $this->home . "/drivers/$class.php";

            if (!file_exists($file)) {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Password plugin: Unable to open driver file ($file)"
                ), true, false);
                return $this->gettext('internalerror');
            }

            include_once $file;

            // try to instantiate class
           //if (!class_exists($class, false) || !method_exists($class, 'save') || !method_exists($class, 'load')) {
           if (!class_exists($class, false)) {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Password plugin: Broken driver $driver"
                ), true, false);
                return $this->gettext('internalerror');
            }
            
            $this->storage = new $class();
        }
    }

    private function _save($activate, $subject, $body)
    {
            $this->_init_storage();
            $result = $this->storage->save($activate, $subject, $body);
            $message = '';
    
            if (is_array($result)) {
                $message = $result['message'];
                $result  = $result['code'];
            }
    
            switch ($result) {
                case OUTOFOFFICE_SUCCESS:
                    return;
                case OUTOFOFFICE_CONFIG_ERROR:
                    $reason = $this->gettext('configerror');
                    break;
                case OUTOFOFFICE_ERROR:
                default:
                    $reason = $this->gettext('internalerror');
            }
    
            if ($message) {
                $reason .= ' ' . $message;
            }
    
            return $reason;
    }
    
    private function _load()
    {
            $this->_init_storage();
            $result = $this->storage->load();
    
            if (is_array($result)) {
                $this->user_values = $result; 
                return;
            }
            else{

                switch ($result) {
                    case OUTOFOFFICE_CONFIG_ERROR:
                        $reason = $this->gettext('configerror');
                        break;
                    case OUTOFOFFICE_ERROR:
                    default:
                        $reason = $this->gettext('internalerror');
                }

                return $reason;
            }
    }
}

