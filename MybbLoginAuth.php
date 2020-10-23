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

if (function_exists('wfLoadExtension')) {
    wfLoadExtension('MybbLoginAuth');
    $wgMessagesDirs['MybbLoginAuth'] = __DIR__ . '/i18n';
    wfWarn(
        'Deprecated PHP entry point used for MybbLoginAuth extension. Please use wfLoadExtension instead, ' .
        'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
    );
    return;
} else {
    die('This version of the MybbLoginAuth extension requires MediaWiki 1.27+');
}
