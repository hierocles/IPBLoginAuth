<?php

/*
    MybbLoginAuth is a MediaWiki extension which authenticates users through a MyBB forums database.
    Copyright (C) 2020 Dylan Henrich

    This program is based on IPBLoginAuth by Frédéric Hannes <https://github.com/FHannes/IPBLoginAuth>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace MybbLoginAuth;
use User;

class MybbAuth
{

    private static $config;

    /**
     * Returns a config singleton object allowing access to the extension's configuration.
     *
     * @return \Config
     */
    public static function getConfig()
    {
        if (self::$config === null) {
            self::$config = \MediaWiki\MediaWikiServices::getInstance()->getConfigFactory()->makeConfig('mybbloginauth');
        }
        return self::$config;
    }

    /**
     * Creates and returns a new mysqli object to access the IPB forum database.
     *
     * @return \mysqli
     */
    public static function getSQL()
    {
        $cfg = MybbAuth::getConfig();
        return @new \mysqli(
            $cfg->get('MybbDBHost'),
            $cfg->get('MybbDBUsername'),
            $cfg->get('MybbDBPassword'),
            $cfg->get('MybbDBDatabase')
        );
    }

    /**
     * Clean up a value (username or password) before using it to query the forum database. A similar function is used
     * in the IPB software to access the database.
     *
     * @param $value
     * @return string
     */
    public static function cleanValue($value)
    {
        if ($value == "") {
            return "";
        }

        $value = preg_replace('/\\\(?!&amp;#|\?#)/', "&#092;", $value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
        $value = str_replace("&#032;", " ", $value);
        $value = str_replace(array("\r\n", "\n\r", "\r"), "\n", $value);
        $value = str_replace("<!--", "&#60;&#33;--", $value);
        $value = str_replace("-->", "--&#62;", $value);
        $value = str_ireplace("<script", "&#60;script", $value);
        $value = str_replace("\n", "<br />", $value);
        $value = str_replace("$", "&#036;", $value);
        $value = str_replace("!", "&#33;", $value);
        // UNICODE
        $value = preg_replace("/&amp;#([0-9]+);/s", "&#\\1;", $value);
        $value = preg_replace('/&#(\d+?)([^\d;])/i', "&#\\1;\\2", $value);

        return $value;
    }

    /**
     * Normalizes a username based on the usernames stored in the forum database.
     *
     * @param $username
     * @return string
     */
    public static function normalizeUsername($username)
    {
        $originalname = $username;
        $cfg = MybbAuth::getConfig();
        $sql = MybbAuth::getSQL();
        try {
            $username = MybbAuth::cleanValue($username);
            $username = $sql->real_escape_string($username);
            $prefix = $cfg->get('MybbDBPrefix');

            // Check underscores
            $us_username = str_replace(" ", "_", $username);
            $stmt = $sql->prepare("SELECT email FROM {$prefix}users WHERE lower(username) = lower(?)");
            if ($stmt) {
                try {
                    $stmt->bind_param('s', $us_username);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $username = $us_username;
                    }
                } finally {
                    $stmt->close();
                }
            }

            // Update user
            $stmt = $sql->prepare("SELECT username FROM {$prefix}users WHERE lower(username) = lower(?)");
            if ($stmt) {
                try {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($name);
                        if ($stmt->fetch()) {
                            $username = User::getCanonicalName($name, 'creatable');
                            if ($username) {
                                return $username;
                            }
                        }
                    }
                } finally {
                    $stmt->close();
                }
            }
        } finally {
            $sql->close();
        }
        return $originalname;
    }

    /**
     * Updates a \User object with data from the MyBB forum database.
     *
     * @param $user
     */
    public static function updateUser(&$user)
    {
        $cfg = MybbAuth::getConfig();
        $sql = MybbAuth::getSQL();
        try {
            $username = MybbAuth::cleanValue($user->getName());
            $username = $sql->real_escape_string($username);
            $prefix = $cfg->get('MybbDBPrefix');
            $name_field = 'username';

            // Check underscores
            $us_username = str_replace(" ", "_", $username);
            $stmt = $sql->prepare("SELECT email FROM {$prefix}users WHERE lower(username) = lower(?)");
            if ($stmt) {
                try {
                    $stmt->bind_param('s', $us_username);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $username = $us_username;
                    }
                } finally {
                    $stmt->close();
                }
            }

            // Update user
            $stmt = $sql->prepare("SELECT usergroup, additionalgroups, email, {$name_field} FROM {$prefix}users WHERE lower(username) = lower(?)");
            if ($stmt) {
                try {
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $stmt->store_result();
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($usergroup, $additionalgroups, $email, $mybbusername);
                        if ($stmt->fetch()) {
                            $user->setEmail($email);
                            if ($usergroup != $cfg->get('MybbGroupValidating')) {
                                $user->confirmEmail();
                            }
                            $user->setRealName($mybbusername);
                            $groups = explode(",", $additionalgroups);
                            $groups[] = $usergroup;
                            $groupmap = $cfg->get('MybbGroupMap');
                            if (is_array($groupmap)) {
                                foreach ($groupmap as $ug_wiki => $ug_mybb) {
                                    $user_has_ug = in_array($ug_wiki, $user->getEffectiveGroups());
                                    if (in_array($ug_mybb, $groups) && !$user_has_ug) {
                                        $user->addGroup($ug_wiki);
                                    } elseif (!in_array($ug_mybb, $groups) && $user_has_ug) {
                                        $user->removeGroup($ug_wiki);
                                    }
                                }
                            }
                            $user->saveSettings();
                        }
                    }
                } finally {
                    $stmt->close();
                }
            }
        } finally {
            $sql->close();
        }
    }

    /**
     * Verifies whether a username is already in use in the MyBB forum database.
     *
     * @param $username
     * @return bool
     */
    public static function userExists($username)
    {
        $sql = MybbAuth::getSQL();
        try {
            if ($sql->connect_errno) {
                return false;
            }

            $username = MybbAuth::cleanValue($username);
            $username = $sql->real_escape_string($username);
            $prefix = $cfg->get('MybbDBPrefix');

            // Check underscores
            $us_username = str_replace(" ", "_", $username);
            $stmt = $sql->prepare("SELECT email FROM {$prefix}users WHERE lower(username) = lower(?) OR lower(username) = lower(?)");
            if ($stmt) {
                try {
                    $stmt->bind_param('ss', $username, $us_username);
                    $stmt->execute();
                    $stmt->store_result();
                    return $stmt->num_rows == 1;
                } finally {
                    $stmt->close();
                }
            } else {
                return false;
            }
        } finally {
            $sql->close();
        }
    }

    /**
     * Verifies if a supplied password matches the password hash in the MyBB database.
     *
     * @param $password - based on user input
     * @param $mybbpassword - md5 hash in MyBB database
     * @param $salt - from MyBB database
     * @return bool
     */
    public static function checkMybbPassword($password, $mybbpassword, $salt)
    {
        $hash = md5(md5($salt).md5($password));
        $compare = md5(md5($salt).$mybbpassword);
        return $hash == $compare;
    }
}
