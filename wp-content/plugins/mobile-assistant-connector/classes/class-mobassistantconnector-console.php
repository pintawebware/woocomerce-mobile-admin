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

class Mobassistantconnector_Console {

    /**
     * List of Runtime errors related to MA Connector
     *
     * @var array
     *
     * @access private
     * @static
     */
    private static $_warnings = array();

    /**
     * Add new warning
     *
     * @param string $message
     *
     * @return void
     *
     * @access public
     * @static
     */
    public static function add($message) {
        self::$_warnings[] = $message;
    }

    /**
     * Check if there is any warning during execution
     *
     * @return boolean
     *
     * @access public
     * @static
     */
    public static function hasIssues() {
        return (count(self::$_warnings) ? true : false);
    }

    /**
     * Get list of all warnings
     *
     * @return array
     *
     * @access public
     * @static
     */
    public static function getWarnings() {
        return self::$_warnings;
    }

    /**
     *
     * @return type
     */
    public static function count() {
        return count(self::$_warnings);
    }

}