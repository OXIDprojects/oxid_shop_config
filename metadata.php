<?php
/**
 * This file is part of OXID Shop Configuration Im-/Exporter module.
 *
 * OXID Module Configuration Im-/Exporter module is free software:
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option] any later version.
 *
 * OXID Module Configuration Im-/Exporter module is distributed in
 * the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
 * for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID Module Configuration Im-/Exporter module.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @category      module
 * @package       oxidshopconfig
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C] OXID eSales AG 2003-2014
 */

/**
 * Metadata version
 */
$sMetadataVersion = '2.0';

/**
 * Module information
 */
$aModule = [
    'id'          => 'oxps/oxidshopconfig',
    'title'       => 'OXID eshop Shop Configuration Im-/Exporter',
    'description' => [
        'de' => 'Tools, um OXID eShop Shop-Konfigurationsdaten zu exportieren, importieren oder zu sichern.',
        'en' => 'Tools to export, backup and import OXID eShop shop configuration data.',
    ],
    'thumbnail'   => 'out/pictures/oxpsmodulesconfig.png',
    'version'     => '0.1.1',
    'author'      => 'OXID Professional Services',
    'url'         => 'http://www.oxid-esales.com',
    'email'       => 'info@oxid-esales.com',
    'extend'      => [],
    'controllers' => [
    ],
    'templates'   => [
    ],
    'events'      => [
    ],
    'settings' => [
        [ 'group' => 'main', 'name' => 'OXPS_OXIDCONFIGIMPORT_SETTING_CONFIGURATION_DIRECTORY', 'type' => 'str', 'value' => '../../../../configurations' ]
    ]
];
