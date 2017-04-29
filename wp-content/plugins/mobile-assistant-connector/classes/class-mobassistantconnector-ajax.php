<?php
/**
 *	This file is part of Mobile Assistant Connector.
 *
 *   Mobile Assistant Connector is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   Mobile Assistant Connector is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with Mobile Assistant Connector.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  @author    eMagicOne <contact@emagicone.com>
 *  @copyright 2014-2017 eMagicOne
 *  @license   http://www.gnu.org/licenses   GNU General Public License
 */

class emaAjax {
    private $key;
    private $callFunction;
    private $userId;
    private $pushIds;
    private $statusValue;
    private $user;
    private $userAllowedActions;
    private $delUserId;
    private $newUserLogin;
    private $newUserPassword;

    public $wpdb;

    /**
     * @param wpdb $wpdb
     */
    public function __construct(wpdb $wpdb)
    {
        $this->wpdb               = $wpdb;

        $this->key                = $this->getPostVar('key', '');
        $this->callFunction       = $this->getPostVar('call_function', '');

        $this->userId             = $this->getPostVar('user_id', '');
        $this->pushIds            = $this->getPostVar('push_ids', '');
        $this->statusValue        = $this->getPostVar('value', '');
        $this->user               = $this->getPostVar('user',  array());
        $this->userAllowedActions = $this->getPostVar('user_allowed_actions', array());
        $this->delUserId          = $this->getPostVar('mac_del_user_id', '');
        $this->newUserLogin       = $this->getPostVar('new_user_login', '');
        $this->newUserPassword    = $this->getPostVar('new_user_password', '');


        if ( ! $this->is_authenticated() ) {
            die( json_encode( 'Authentication error' ) );
        }

    }

    public function getAjaxResult()
    {
        $result = false;

        if ( $this->callFunction ) {
            switch ($this->callFunction) {
                case 'mac_delete_user':
                    $result = $this->macDeleteUser();
                    break;
                case 'mac_add_user':
                    $result = $this->macAddUser();
                    break;
                case 'mac_save_user':
                    $result = $this->macSaveUser();
                    break;
                case 'mac_get_users':
                    $result = $this->macGetUsers();
                    break;
                case 'mac_get_user_data':
                    $result = $this->macGetUserData();
                    break;
                case 'mac_get_devices':
                    $result = $this->macGetDevices();
                    break;
                case 'change_status':
                    $result = $this->changeDeviceStatus();
                    break;
                case 'delete_device':
                    $result = $this->deleteDevice();
                    break;
            }
//            $result = $this->{$this->callFunction};
        } else {
            $result = json_encode( 'error' );
        }

        return (string)$result;
    }

    private function getPostVar($var, $default_value)
    {
        return (isset($_POST[$var]) ? $_POST[$var] : $default_value);
    }

    private function is_authenticated() {
//        $login_data = get_option( 'mobassistantconnector' );
        if ( hash( 'sha256', MOBASSIS_KEY  . AUTH_KEY )  == $this->key ) {
            return true;
        }
    
        return false;
    }

    private function macDeleteUser()
    {
        if (!empty($this->delUserId)) {
            $user = $this->wpdb->get_results(
                $this->wpdb->prepare("SELECT `user_id` FROM {$this->wpdb->prefix}mobileassistant_users WHERE `user_id` = %s", $this->delUserId)
            );
    
            if ($user) {
    
                $result = $this->wpdb->query(
                    $this->wpdb->prepare(
                        "DELETE FROM `{$this->wpdb->prefix}mobileassistant_users` WHERE `user_id` = %s",
                        $this->delUserId
                    )
                );
    
                if (false !== $result) {
                    $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}mobileassistant_push_settings WHERE `user_id` = {$this->delUserId}");
                    $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}mobileassistant_session_keys WHERE `user_id` = {$this->delUserId}");
    
                    Mobassistantconnector_Functions::delete_empty_devices();
                    Mobassistantconnector_Functions::delete_empty_accounts();
    
                    $result = json_encode(array('success' => true, 'user_id' => $this->delUserId));
                } else {
                    $result = json_encode(array('success' => false, 'error' => 'User cannot be deleted.'));
                }
    
            } else {
                $result = json_encode(array('success' => true, 'user_id' => $this->delUserId));
            }
        } else {
            $result = json_encode(array('success' => false, 'error' => 'Missed User ID.'));
        }
        return $result;
    }

    private function macAddUser()
    {
        $user = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT `user_id` FROM {$this->wpdb->prefix}mobileassistant_users WHERE `username` = %s", $this->newUserLogin ) );
    
        if ( ! $user ) {
    
            $all_options = array();
    
            $groups = Mobassistantconnector_Functions::get_default_actions();
            foreach ($groups as $option_group) {
                foreach ($option_group as $option) {
                    $all_options[] = $option['code'];
                }
            }
    
            $sql = $this->wpdb->prepare(
                "INSERT INTO `{$this->wpdb->prefix}mobileassistant_users` (`username`, `password`, `allowed_actions`, `qr_code_hash`, `status` )
                                       VALUES (%s, %s, %s, %s, 1)", $this->newUserLogin, md5($this->newUserPassword), implode(';', $all_options),
                hash('sha256', time()), 1
            );
    
            $result = $this->wpdb->query($sql);
    
            if ( false !== $result ) {
                $user_id = $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT `user_id` FROM `{$this->wpdb->prefix}mobileassistant_users` WHERE `username` = %s LIMIT 1",
                        $this->newUserLogin
                    )
                );
    
                $result = json_encode( array('success' => true, 'user_id' => $user_id) );
            }
        } else {
            $result = json_encode(array('success' => false, 'error' => 'User with name (' . $this->newUserLogin . ') already exists.'));
        }
    
        return $result;
    }

    private function macSaveUser()
    {
        $user_data = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT `user_id`, `password`  FROM {$this->wpdb->prefix}mobileassistant_users WHERE `user_id` = %s", $this->user['user_id'] ) , ARRAY_A);
    
        if ( ! $user_data ) {
            $user_data = array();
        } else {
            $user_data = array_shift($user_data);
        }
    
        if ( !empty($user_data) ) {
    
            $updated_user = $this->user;
            if ($user_data['password'] !== $this->user['password']) {
                $updated_user['password'] = md5($this->user['password']);
            }
    
            $allowed_actions = array_keys($this->userAllowedActions);
    
            $sql = $this->wpdb->prepare(
                "UPDATE `{$this->wpdb->prefix}mobileassistant_users`
                           SET `username` = %s, `password` = %s, `allowed_actions` = %s, `qr_code_hash` = %s, `status` = %d
                           WHERE `user_id` = %d", $updated_user['username'], $updated_user['password'], implode(';', $allowed_actions),
                hash('sha256', time()), (int)$updated_user['status'], $this->user['user_id']
            );
    
            $result = $this->wpdb->query($sql);
    
            if (!in_array('push_notification_settings_new_order', $allowed_actions)
                &&  !in_array('push_notification_settings_new_customer', $allowed_actions)
                &&  !in_array('push_notification_settings_order_statuses', $allowed_actions)
            ) {
                $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}mobileassistant_session_keys WHERE `user_id` = {$user_data['user_id']}");
    
                Mobassistantconnector_Functions::delete_empty_devices();
                Mobassistantconnector_Functions::delete_empty_accounts();
            }
    
            if ( false !== $result ) {
                $result = json_encode( array('success' => true, 'user_id' => $this->user['user_id']) );
            } else {
                $result = json_encode(array('success' => false, 'error' => 'Cannot save the user.'));
            }
        } else {
            $result = json_encode(array('success' => false, 'error' => 'Cannot save the user.'));
        }
    
        return $result;
    }

    private function macGetUsers()
    {
        $users = $this->wpdb->get_results( "SELECT
                mu.user_id,
                mu.username,
                mu.password,
                mu.allowed_actions,
                mu.qr_code_hash,
                mu.status
            FROM `{$this->wpdb->prefix}mobileassistant_users` mu", ARRAY_A );

        if ( ! $users ) {
            $users = array();
        }
    
    //    $users_count = count($users);
    
        $users     = $this->replace_null( $users );
    //    $devices = form_devices( $devices, $statuses );
    
        return json_encode( $users );
    }

    private function macGetUserData()
    {
        $user = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT
                mu.user_id,
                mu.username,
                mu.password,
                mu.allowed_actions,
                mu.qr_code_hash,
                mu.status,
                mps.setting_id,
                mps.app_connection_id,
                mps.push_new_order,
                mps.push_order_statuses,
                mps.push_new_customer,
                mps.device_unique_id,
                -- mps.status,
                mps.push_currency_code,
                md.device_unique_id,
                md.account_id,
                md.device_name,
                md.last_activity,
                ma.account_email
                -- ma.`status`
            FROM `{$this->wpdb->prefix}mobileassistant_users` mu
            LEFT JOIN `{$this->wpdb->prefix}mobileassistant_push_settings` mps
               ON mu.user_id = mps.user_id
            LEFT JOIN `{$this->wpdb->prefix}mobileassistant_devices` md
               ON md.device_unique_id = mps.device_unique_id
            LEFT JOIN `{$this->wpdb->prefix}mobileassistant_accounts` ma
               ON ma.id = md.account_id
            WHERE mu.user_id = %d", $this->userId)
            , ARRAY_A );
    
        if ( ! $user ) {
            $user = array();
        } else {
            $user = array_shift($user);
            $qr_config = array(
                'url' => get_site_url(),
                'login' => $user['username'],
                'password' => $user['password']
            );
    
            $qr_config['url'] = str_replace("http://", "", $qr_config['url']);
            $qr_config['url'] = str_replace("https://", "", $qr_config['url']);
    
            $user['qr_code_data'] = base64_encode(json_encode($qr_config));
        }
    
    //    $users_count = count($users);
    
        if (!empty($user['allowed_actions'])) {
            $user_actions = explode(';', $user['allowed_actions']);
            $user['allowed_actions'] = $user_actions;
        }
    
    //    $user     = replace_null( $user );
    //    $devices = form_devices( $devices, $statuses );
    
        return json_encode( array($user) );
    }

    private function macGetDevices()
    {
        $devices = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT
                mpn.`setting_id` AS id,
                mpn.`push_new_order` AS new_order,
                mpn.`push_new_customer` AS new_customer,
                mpn.`push_order_statuses` AS order_statuses,
                mpn.`app_connection_id`,
                mpn.`status`,
                mpn.`device_unique_id`,
                md.`device_name`,
                md.`last_activity`,
                md.`account_id`,
                ma.`account_email`,
                ma.`status` AS account_status
            FROM `{$this->wpdb->prefix}mobileassistant_push_settings` mpn
            LEFT JOIN `{$this->wpdb->prefix}mobileassistant_devices` md ON md.`device_unique_id` = mpn.`device_unique_id`
            INNER JOIN `{$this->wpdb->prefix}mobileassistant_accounts` ma ON ma.`id` = md.`account_id`
            LEFT JOIN `{$this->wpdb->prefix}mobileassistant_users` mu ON mpn.`user_id` = mu.`user_id`
            WHERE mu.user_id = %d", $this->userId), ARRAY_A );
    
        if ( ! $devices ) {
            $devices = array();
        }
    
        $devices     = $this->replace_null( $devices );
        $statuses_db = _get_order_statuses();
        $statuses    = array();
    
        foreach ( $statuses_db as $code => $status ) {
            $statuses[ $code ] = $status;
        }
    
        $devices = $this->form_devices( $devices, $statuses );
    
        return json_encode( $devices );
    }

    private function changeDeviceStatus()
    {
        $ids = $this->prepare_ids( $this->pushIds );

        if ( ! $ids ) {
            return json_encode( 'Parameters are incorrect' );
        }
    
        $result = $this->wpdb->query( $this->wpdb->prepare( "UPDATE {$this->wpdb->prefix}mobileassistant_push_settings SET `status` = %d WHERE `setting_id` IN ({$ids})", $this->statusValue ) );
    
        if ( false !== $result ) {
            return json_encode( 'success' );
        }
    
        return json_encode( 'Some error occurred' );
    }

    private function deleteDevice()
    {
        $ids = $this->prepare_ids( $this->pushIds );
    
        if ( ! $ids ) {
            return json_encode( 'Parameters are incorrect' );
        }
    
        $result = $this->wpdb->query( "DELETE FROM {$this->wpdb->prefix}mobileassistant_push_settings WHERE `setting_id` IN ({$ids})" );
        Mobassistantconnector_Functions::delete_empty_devices();
    
        if ( false !== $result ) {
            return json_encode( 'success' );
        }
    
        return json_encode( 'Some error occurred' );
    }

    private function form_devices( $devices, $statuses ) 
    {
        $count_devices  = count( $devices );
        $devices_output = array();
    
        for ( $i = 0; $i < $count_devices; $i++ ) {
            $device_unique = ! $devices[ $i ]['device_unique_id'] ? 'Unknown' : $devices[ $i ]['device_unique_id'];
    
            if ( $devices[ $i ]['order_statuses'] ) {
                if ( (int) $devices[ $i ]['order_statuses'] == -1 ) {
                    $devices[ $i ]['order_statuses'] = 'All';
                } else {
                    $push_statuses       = explode( '|', $devices[ $i ]['order_statuses'] );
                    $count_push_statuses = count( $push_statuses );
                    $view_statuses       = array();
    
                    for ( $j = 0; $j < $count_push_statuses; $j++ ) {
                        if ( isset( $statuses[ $push_statuses[ $j ] ] ) ) {
                            $view_statuses[] = $statuses[ $push_statuses[ $j ]];
                        }
                    }
    
                    $devices[ $i ]['order_statuses'] = implode( ', ', $view_statuses );
                }
            }
    
            if ( $devices[ $i ]['last_activity'] == '0000-00-00 00:00:00' ) {
                $devices[ $i ]['last_activity'] = '';
            }
    
            if ( $device_unique == 'Unknown' ) {
                $devices[ $i ]['device_name'] = 'Unknown';
            }
    
            $devices_output[ $device_unique ]['device_name']   = ! $devices[ $i ]['device_name'] ? '-' : $devices[ $i ]['device_name'];
            $devices_output[ $device_unique ]['account_email'] = ! $devices[ $i ]['account_email'] ? '-' : $devices[ $i ]['account_email'];
            $devices_output[ $device_unique ]['last_activity'] = ! $devices[ $i ]['last_activity'] ? '-' : $devices[ $i ]['last_activity'];
            $devices_output[ $device_unique ]['pushes'][]      = array(
                'id'                => $devices[ $i ]['id'],
                'new_order'         => $devices[ $i ]['new_order'],
                'new_customer'      => $devices[ $i ]['new_customer'],
                'order_statuses'    => ! $devices[ $i ]['order_statuses'] ? '-' : $devices[ $i ]['order_statuses'],
                'app_connection_id' => $devices[ $i ]['app_connection_id'],
                'status'            => $devices[ $i ]['status'],
            );
        }
    
        return $devices_output;
    }

    private function replace_null( $data )
    {
        if ( ! is_array( $data ) ) {
            $data = array();
        }
    
        foreach ( $data as $index => $values ) {
            foreach ( $values as $key => $value ) {
                if ( $value === null ) {
                    $data[ $index ][ $key ] = '';
                }
            }
        }
    
        return $data;
    }

    private function prepare_ids( $data )
    {
        if ( ! $data ) {
            return false;
        }
    
        $ids   = array();
        $arr   = explode( ',', $data );
        $count = count( $arr );
    
        for ( $i = 0; $i < $count; $i++ ) {
            $ids[] = (int) trim( $arr[ $i ] );
        }
    
        return implode( ',', $ids );
    }

}