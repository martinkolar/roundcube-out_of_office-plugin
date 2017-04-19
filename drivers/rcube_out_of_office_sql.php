<?php

/**
 * SQL Out of Office Driver
 *
 * Driver for Out of Office messages stored in SQL database
 *
 * @version 1.0
 * @author Martin Kolar <kolar@m2system.cz>
 *
 */

class rcube_out_of_office_sql
{
    private $db;
    
    function load()
    {
        $rcmail = rcmail::get_instance();
        $values = array();
        
        $sql_activate = $rcmail->config->get('activate_query_show');
        $sql_msg = $rcmail->config->get('message_query_show') ;
        $sql_activate_enabled = $rcmail->config->get('activate_value_enabled'); 
        $sql_activate_disabled = $rcmail->config->get('activate_value_disabled'); 
        if(!(isset($sql_activate) && isset($sql_msg) && isset($sql_activate_enabled) &&isset($sql_activate_disabled))){
            return OUTOFOFFICE_CONFIG_ERROR;
        }
        
        if($this->_db_connect() !== OUTOFOFFICE_SUCCESS)
            return OUTOFOFFICE_ERROR;

        if(!($sql_activate = $this->_sql_replace($sql_activate)))
            return OUTOFOFFICE_ERROR;
        $res = $this->db->query($sql_activate);
        
        while ($res && ($arr = $this->db->fetch_array($res))) {
            $value_activate = $arr[0];
        
            $values['activate'] = $value_activate == $sql_activate_enabled ? true : false;
        }
        unset($res);

        if(!($sql_msg = $this->_sql_replace($sql_msg)))
            return OUTOFOFFICE_ERROR;
        $res = $this->db->query($sql_msg);
        
        while ($res && ($arr = $this->db->fetch_assoc($res))) {
            $values['subject'] = $arr['subject'];
            $values['body'] = $arr['body'];
        }

        return $values;
    }

    function save($activate, $subject, $body)
    {
        $rcmail = rcmail::get_instance();

        $sql_activate = $rcmail->config->get('activate_query_set');
        $sql_msg = $rcmail->config->get('message_query_set') ;
        $sql_activate_enabled = $rcmail->config->get('activate_value_enabled'); 
        $sql_activate_disabled = $rcmail->config->get('activate_value_disabled'); 
        if(!(isset($sql_activate) && isset($sql_msg) && isset($sql_activate_enabled) && isset($sql_activate_disabled))){
            return OUTOFOFFICE_CONFIG_ERROR;
        }

        $sql_activate_value = $activate === true ? $sql_activate_enabled : $sql_activate_disabled;

        if($this->_db_connect() !== OUTOFOFFICE_SUCCESS)
            return OUTOFOFFICE_ERROR;

        $sql_activate = str_replace('%a', $this->db->quote($sql_activate_value, 'text'), $sql_activate);
        if(!($sql_activate = $this->_sql_replace($sql_activate)))
            return OUTOFOFFICE_ERROR;
        $res = $this->db->query($sql_activate);
        
        if ($this->db->is_error() && $this->db->affected_rows($res) != 1) {
            return OUTOFOFFICE_ERROR;
        }
        unset($res);


        $sql_msg = str_replace('%s', $this->db->quote($subject, 'text'), $sql_msg);
        $sql_msg = str_replace('%b', $this->db->quote($body, 'text'), $sql_msg);
        if(!($sql_msg = $this->_sql_replace($sql_msg)))
            return OUTOFOFFICE_ERROR;
        $res = $this->db->query($sql_msg);

        if (!$this->db->is_error()) {
                return OUTOFOFFICE_SUCCESS;
        }
        else {
                // This is the good case: 1 row updated
                if ($this->db->affected_rows($res) == 1)
                    return OUTOFOFFICE_SUCCESS;
                // @TODO: Some queries don't affect any rows
                // Should we assume a success if there was no error?
        }

        return OUTOFOFFICE_ERROR;
    }

    private function _db_connect()
    {
        if(!$this->db)
        {
            $rcmail = rcmail::get_instance();
            if ($dsn = $rcmail->config->get('password_db_dsn')) {
                $this->db = rcube_db::factory($dsn, '', false);
                $this->db->set_debug((bool)$rcmail->config->get('sql_debug'));
            }
            else {
                $this->db = $rcmail->get_dbh();
            }

            if ($this->db->is_error()) {
                return OUTOFOFFICE_ERROR;
            }
        }
        return OUTOFOFFICE_SUCCESS;
    }

    private function _sql_replace($sql){
        $rcmail = rcmail::get_instance();
        $this->_db_connect();

        $local_part  = $rcmail->user->get_username('local');
        $domain_part = $rcmail->user->get_username('domain');
        $username    = $_SESSION['username'];
        $host        = $_SESSION['imap_host'];

        if ($rcmail->config->get('password_idn_ascii')) {
            $domain_part = rcube_utils::idn_to_ascii($domain_part);
            $username    = rcube_utils::idn_to_ascii($username);
            $host        = rcube_utils::idn_to_ascii($host);
        }
        else {
            $domain_part = rcube_utils::idn_to_utf8($domain_part);
            $username    = rcube_utils::idn_to_utf8($username);
            $host        = rcube_utils::idn_to_utf8($host);
        }
        
        $sql = str_replace('%l', $this->db->quote($local_part, 'text'), $sql);
        $sql = str_replace('%d', $this->db->quote($domain_part, 'text'), $sql);
        $sql = str_replace('%u', $this->db->quote($username, 'text'), $sql);
        $sql = str_replace('%h', $this->db->quote($host, 'text'), $sql);

        return $sql;
    }
}
