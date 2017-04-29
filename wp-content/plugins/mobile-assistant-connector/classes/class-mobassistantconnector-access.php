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

class Mobassistantconnector_Access
{
    const HASH_ALGORITHM     = 'sha256';
    const MAX_LIFETIME       = 86400; /* 24 hours */
    const TABLE_SESSION_KEYS = 'mobileassistant_session_keys';
    const TABLE_FAILED_LOGIN = 'mobileassistant_failed_login';
    const TABLE_USERS        = 'mobileassistant_users';

    public static function clear_old_data()	{
        global $wpdb;

        $timestamp       = time();
        $date_clear_prev = get_option( 'mobassistantconnector_cl_date' );
        $date            = date( 'Y-m-d H:i:s', ( $timestamp - self::MAX_LIFETIME ) );

        if ( $date_clear_prev === false || ( $timestamp - (int) $date_clear_prev ) > self::MAX_LIFETIME ) {
            $wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . self::TABLE_SESSION_KEYS . '` WHERE `date_added` < %s', $date ) );
            $wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . self::TABLE_FAILED_LOGIN . '` WHERE `date_added` < %s', $date ) );
            $wpdb->update( $wpdb->options, array( 'option_value' => $timestamp ), array( 'option_name' => 'mobassistantconnector_cl_date' ), '%d', '%s' );
        }
    }

    public static function get_session_key( $hash, $user_id = null )
    {
        if (!$user_id) {
            $login_data = self::check_auth($hash);

            if ($login_data) {
                $user_id = (int)$login_data['user_id'];
            }
        }

        if ($user_id) {
            return self::generate_session_key($user_id);
        }

        self::add_failed_attempt();

/*        if ( hash( self::HASH_ALGORITHM, $login_data['login'] . $login_data['pass'] ) == $hash ) {
            return self::generate_session_key( $login_data['login'] );
        } else {
            self::add_failed_attempt();
        }*/

        return false;
    }

    public static function check_session_key( $key, $user_id = false )
    {
        global $wpdb;

        $timestamp = time();
        $values = array(
            $key,
            date( 'Y-m-d H:i:s', ( $timestamp - self::MAX_LIFETIME ) ),
        );
        if ($user_id) {
            $user_cond = ' AND u.`user_id` = %s';
            $values[] = $user_id;
        }

        $db_key = $wpdb->get_var( $wpdb->prepare( 'SELECT sk.`session_key` FROM `' . $wpdb->prefix . self::TABLE_SESSION_KEYS. '` AS sk
                 LEFT JOIN `' . $wpdb->prefix . self::TABLE_USERS . '` AS u
                 ON sk.`user_id` = u.user_id
                 WHERE sk.`session_key` = %s AND sk.`date_added` > %s AND (u.`status` = 1 or u.`status` = null)' . (isset($user_cond) ? $user_cond : '' ) . ' LIMIT 1', $values ) );

        if ( $db_key ) {
            return true;
        } else {
            self::add_failed_attempt();
        }

        return false;
    }

    private static function generate_session_key( $user_id )
    {
        global $wpdb;

        $timestamp = time();

        $key = self::get_session_key_by_user_id($user_id);

        if ($key) {
            return $key;
        }

        $date = date( 'Y-m-d H:i:s', $timestamp );
        $key = hash( self::HASH_ALGORITHM, AUTH_KEY . $timestamp );
        $wpdb->insert( $wpdb->prefix . self::TABLE_SESSION_KEYS, array( 'session_key' => $key, 'user_id' => $user_id, 'date_added' => $date),
            array( '%s', '%s', '%s') );

        return $key;
    }

    private static function get_session_key_by_user_id( $user_id )
    {
        global $wpdb;

        $date = date( 'Y-m-d H:i:s', (time() - self::MAX_LIFETIME) );
        $session_key = $wpdb->get_var( $wpdb->prepare( 'SELECT `session_key` FROM `' . $wpdb->prefix . self::TABLE_SESSION_KEYS
            . '` WHERE `user_id` = %s AND `date_added` > %s ', $user_id, $date ) );

        return $session_key;
    }

    public static function get_user_id_by_session_key( $session_key )
    {
        global $wpdb;

        $user_id = $wpdb->get_var( $wpdb->prepare( 'SELECT `user_id` FROM `' . $wpdb->prefix . self::TABLE_SESSION_KEYS
            . '` WHERE `session_key` = %s ', $session_key ) );

        return $user_id;
    }

    public static function add_failed_attempt()
    {
        global $wpdb;

        $timestamp = time();
        $wpdb->insert( $wpdb->prefix . self::TABLE_FAILED_LOGIN,
            array( 'ip' => $_SERVER['REMOTE_ADDR'], 'date_added' => date( 'Y-m-d H:i:s', $timestamp ) ),
            array( '%s', '%s' ) );

        // Get count of failed attempts for last 24 hours and set delay
        $count_failed_attempts = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(`id`) FROM `' . $wpdb->prefix . self::TABLE_FAILED_LOGIN
            . '` WHERE `ip` = %s AND `date_added` > %s', $_SERVER['REMOTE_ADDR'], date( 'Y-m-d H:i:s', ( $timestamp - self::MAX_LIFETIME ) ) ) );
        self::set_delay( (int) $count_failed_attempts );
    }

    public static function check_auth( $hash, $logger = null )
    {
        global $wpdb;

        $sql = "SELECT `user_id`, `username`, `password` FROM `{$wpdb->prefix}mobileassistant_users` WHERE `status` = %s";
        $query = $wpdb->prepare( $sql, '1' );

        $login_data = $wpdb->get_results($query, ARRAY_A);

        if ($login_data) {
            foreach ($login_data as $user) {
                if (hash(self::HASH_ALGORITHM, $user['username'] . $user['password']) == $hash) {
                    return $user;
                }
            }
        }

        if ($logger) {
            self::log_me("Hash accepted is incorrect");
        }

        return false;
    }

    public static function get_allowed_actions_by_user_id( $user_id )
    {
        global $wpdb;

        $result = array();
        $actions = $wpdb->get_var( $wpdb->prepare( 'SELECT `allowed_actions` FROM `' . $wpdb->prefix . self::TABLE_USERS
            . '` WHERE `user_id` = %s ', $user_id ) );

        if ($actions) {
            $result = explode(';', $actions);
        }

        return $result;
    }

    public static function get_allowed_actions_by_session_key( $session_key )
    {
        global $wpdb;

        $result = array();
        $actions = $wpdb->get_var( $wpdb->prepare( 'SELECT `allowed_actions` '
            . 'FROM `' . $wpdb->prefix . self::TABLE_SESSION_KEYS . '` s'
            . ' LEFT JOIN `' . $wpdb->prefix . self::TABLE_USERS . '` u'
            . ' ON s.`user_id`  = u.`user_id`'
            . ' WHERE s.`session_key` = %s ', $session_key ) );

        if ($actions) {
            $result = explode(';', $actions);
        }

        return $result;
    }

    public static function log_me($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            error_log('Mobile Assistant LOG: ' . $message);
        }
    }

    private static function set_delay( $count_attempts )
    {
        if ( $count_attempts <= 10 )
            sleep( 1 );
        elseif ( $count_attempts <= 20 )
            sleep( 2 );
        elseif ( $count_attempts <= 50 )
            sleep( 5 );
        else
            sleep( 10 );
    }

}