<?php
/**
 * This file is part of OXID Module Configuration Im-/Exporter module.
 *
 * OXID Module Configuration Im-/Exporter module is free software:
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
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
 * @package       modulesconfig
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */

namespace OxidProfessionalServices\OxidShopConfig\Core;

use OxidEsales\Eshop\Core\DatabaseProvider;
use phpDocumentor\Reflection\Types\String_;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigExport
 * Implements functionality for the ExportCommand
 */
class ConfigExport extends CommandBase
{

    /**
     * executes all functionality which is necessary for a call of OXID console config:export
     *
     */
    public function executeConsoleCommand()
    {
        try {
            $this->init();

            $aGlobalExcludeFields = $this->getGlobalExcludedFields();

            $aReturn = $this->getCommonConfigurationValues($aGlobalExcludeFields);


            $aShops = $this->writeDataToFileSeperatedByShop($this->getConfigDir(), $aReturn);

            $aReturn = $this->getEnvironmentSpecificConfigurationValues();

            $this->writeEnvironmentSpecificConfigurationValues($aReturn);

            $this->writeMetaConfigFile($aShops);

            $this->getDebugOutput()->writeLn("done");
        } catch (\RuntimeException $e) {
            $this->getDebugOutput()->writeLn("Could not complete");
            $this->getDebugOutput()->writeLn($e->getMessage());
            $this->getDebugOutput()->writeLn($e->getTraceAsString());
        } catch (\oxFileException $oEx) {
            $this->getDebugOutput()->writeLn("Could not complete");
            $this->getDebugOutput()->writeLn($oEx->getMessage());
        }
    }

    /**
     * @param array $aConfigFields
     * @param bool  $blIncludeMode if true include the fields, else exclude them.
     *
     * @return array
     */
    protected function getConfigValues($aConfigFields, $blIncludeMode)
    {
        $sIncludeMode = $blIncludeMode ? '' : 'NOT';
        $sSql = "SELECT oxvarname, oxvartype, %s as oxvarvalue, oxmodule, oxshopid, disp.oxvarconstraint, disp.oxgrouping, disp.oxpos
                 FROM oxconfig as cfg
                 LEFT JOIN oxconfigdisplay as disp
                 ON cfg.oxmodule=disp.oxcfgmodule AND cfg.oxvarname=disp.oxcfgvarname
                 WHERE cfg.oxvarname $sIncludeMode IN ('%s') order by oxshopid asc, oxmodule ASC, oxvarname ASC";

        $sSql = sprintf(
            $sSql,
            \oxRegistry::getConfig()->getDecodeValueQuery(),
            implode("', '", $aConfigFields)
        );

        $aConfigValues  = \oxDb::getDb(\oxDb::FETCH_MODE_ASSOC)->getAll($sSql);
        $aGroupedValues = $this->groupValues($aConfigValues);

        $this->addShopConfig($aGroupedValues, $aConfigFields, $blIncludeMode);
        $aGroupedValues = $this->withoutDefaults($aGroupedValues);

        return $aGroupedValues;
    }

    /**
     * @param $aGroupedValues
     * @param $aConfigFields
     * @param $blInclude_mode
     */
    protected function addShopConfig(& $aGroupedValues, $aConfigFields, $blInclude_mode)
    {
        $aShops = \oxDb::getDb(\oxDb::FETCH_MODE_ASSOC)->getAll('SELECT * FROM `oxshops` ORDER BY oxid ASC');
        foreach ($aShops as $aShop) {
            $id = $aShop['OXID'];
            unset ($aShop['OXID']);
            unset ($aShop['OXTIMESTAMP']);
            foreach ($aShop as $sVarName => $sVarValue) {
                $blFieldConfigured = in_array($sVarName, $aConfigFields);
                $blIncludeField    = $blInclude_mode && $blFieldConfigured;
                $blIncludeField    = $blIncludeField || (!$blInclude_mode && !$blFieldConfigured);
                if ($blIncludeField) {
                    $aGroupedValues[$id]['oxshops'][$sVarName] = $sVarValue;
                }
            }
        }
    }



    /**
     * @param $aGroupedValues
     *
     * @return mixed
     */
    protected function withoutDefaults(&$aGroupedValues)
    {
        foreach ($aGroupedValues as $sShopId => &$aShopConfig) {
            $aGeneralConfig = &$aShopConfig[$this->sNameForGeneralShopSettings];

            $aDefaultGeneralConfig = $this->aDefaultConfig[$this->sNameForGeneralShopSettings];
            foreach ($aGeneralConfig as $sVarName => $mCurrentValue) {
                $mDefaultValue = isset($aDefaultGeneralConfig[$sVarName]) ? $aDefaultGeneralConfig[$sVarName] : null;
                if ($mCurrentValue === $mDefaultValue) {
                    unset($aGeneralConfig[$sVarName]);
                }
            }

            if (array_key_exists('theme', $aShopConfig)) {
                $aCurrentThemeConfigs = &$aShopConfig['theme'];
                $aDefaultThemeConfigs = $this->aDefaultConfig['theme'];

                $currentParentTheme = $aGeneralConfig['sTheme'];
                $currentTheme = $aGeneralConfig['sCustomTheme'];

                foreach ($aCurrentThemeConfigs as $sTheme => &$aThemeConfig) {
                    if ($sTheme != $currentParentTheme && $currentTheme != $sTheme) {
                        unset($aCurrentThemeConfigs[$sTheme]);
                        continue;
                    }

                    $aDefaultThemeConfig = isset($aDefaultThemeConfigs[$sTheme]) ? $aDefaultThemeConfigs[$sTheme] : null;
                    foreach ($aThemeConfig as $sVarName => $mCurrentValue) {
                        if (array_key_exists($sVarName,$aGeneralConfig)) {
                            $this->output->writeln("config '$sVarName' is in theme and in general namespace use --force-cleanup to repair");
                            if ($this->input->getOption('force-cleanup') ) {
                                $sSql = "DELETE FROM oxconfig WHERE oxmodule = '' AND oxvarname = ? AND oxshopid = ?";
                                DatabaseProvider::getDb()->execute($sSql,[$sVarName,$sShopId]);
                            }
                            unset($aGeneralConfig[$sVarName]);
                        }
                        if ($aDefaultThemeConfig != null) {
                            $mDefaultValue = $aDefaultThemeConfig[$sVarName];
                            if ($mCurrentValue === $mDefaultValue) {
                                unset($aThemeConfig[$sVarName]);
                                if (count($aThemeConfig) == 0) {
                                    unset($aCurrentThemeConfigs[$sTheme]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $aGroupedValues;
    }

    /**
     * Group values by shop id and type (i.e theme or general shop settings).
     * @param array $aConfigValues
     * @return array of the form
     * shopId=> [GeneralShopSettings => [ values ], theme => [ theme name => [values] ] ]
     */
    protected function groupValues($aConfigValues)
    {
        $aGroupedValues = array();
        foreach ($aConfigValues as $k => $aConfigValue) {
            $sShopId   = $aConfigValue['oxshopid'];
            $sVarName  = $aConfigValue['oxvarname'];
            $sVarType  = $aConfigValue['oxvartype'];
            $mVarValue = $aConfigValue['oxvarvalue'];
            $sVarConstraints = $aConfigValue['oxvarconstraint'];
            $sVarGrouping = $aConfigValue['oxgrouping'];
            $sVarPos = $aConfigValue['oxpos'];
            $sModule   = $aConfigValue['oxmodule'];
            //sModule is of the form either '', or 'theme:<name of theme> or 'module:<name of module>'
            $aParts    = explode(':', $sModule);
            $sSection  = $aParts[0];
            $sModule   = isset($aParts[1]) ? $aParts[1] : '';

            // We don't want module settings
            if ($sSection == 'module'){
                continue;
            }


            if (in_array($sVarType, array('aarr', 'arr'))) {
                $mVarValue = unserialize($mVarValue);
                if (!is_array($mVarValue)) {
                    $this->output->writeLn(
                        "[warning] $sVarName is not array: '$mVarValue' convert to empty array (shop: $sShopId)"
                    );
                    $mVarValue = array();
                }
            }

            //general shop settings
            if ($sSection == "") {


                $mVarValue = $this->varValueWithTypeInfo(
                    $sVarName,
                    $mVarValue,
                    $sVarType
                );

                $sSection                                       = $this->sNameForGeneralShopSettings;
                $aGroupedValues[$sShopId][$sSection][$sVarName] =
                    $mVarValue;
            } else {
                    $mVarValue = $this->varValueWithTypeInfo($sVarName, $mVarValue, $sVarType);

                if ($sSection == 'theme') {
                    $mVarValue = $this->varValueWithThemeDisplayInfo($sVarName, $mVarValue, $sVarType, $sVarConstraints, $sVarGrouping, $sVarPos);
                }
                if ($sModule) {
                        $aGroupedValues[$sShopId][$sSection][$sModule][$sVarName] =
                            $mVarValue;

                } else {
                    $this->output->writeLn(
                        "incompatible section '$sSection' found ignoring config value '$sVarName'
                    use sql: DELETE FROM oxconfig WHERE oxmodule = '$sSection' to clean up if it is trash.;
                    "
                    );
                }
            }
        }

        return $aGroupedValues;
    }

    protected function varValueWithTypeInfo($sVarName, $mVarValue, $sVarType)
    {
        if ($sVarType === 'aarr' && count($mVarValue) > 1) {
            //if array contains more than one item it can be distinguished from the assoc array we use for type
        } elseif ($sVarType === 'arr') {
            // arrays can be recognised
        } else {
            // default type
            $typeInfoNeeded = true;
            $boolPrefix = substr($sVarName, 0, 2) === "bl";

            if ($sVarType == 'str' && !$boolPrefix) {
                $typeInfoNeeded = false;
            }

            if ($sVarType == 'select' && !$boolPrefix) {
                $typeInfoNeeded = false;
            }

            if ($sVarType == 'bool' && $boolPrefix) {
                $typeInfoNeeded = false;
            }

            if ($sVarType == 'bool' && ($mVarValue === '1' || $mVarValue === '' || $mVarValue === 'true' || $mVarValue === 'false')) {
                $mVarValue = (bool) $mVarValue;
                $typeInfoNeeded = false;
            }

            if ($typeInfoNeeded) {
                $mVarValue = array($sVarType => $mVarValue);
            }
        }

        return $mVarValue;
    }

    /**
     * @param string $sDirName
     * @param array  $aData
     *
     * @return array
     */
    protected function writeDataToFileSeperatedByShop($sDirName, $aData)
    {
        $aShops = array();
        foreach ($aData as $sShop => $aShopConfig) {
            $sFileName      = '/' . 'shop' . $sShop . '.' . $this->getFileExt();
            $aShops[$sShop] = $sFileName;
            $this->writeDataToFile(
                $sDirName . $sFileName,
                $aShopConfig
            );
        }

        return $aShops;
    }

    /**
     * IM* field getter. Prepared for later when Im fields shouldn't be exported.
     *
     * @return string[]
     */
    protected function _getImFields()
    {
        return [
            'IMA',
            'IMD',
            'IMS'
        ];
    }

    /**
     * @param string $sFileName
     * @param array  $aData
     */
    protected function writeDataToFile($sFileName, $aData)
    {
        $exportFormat = $this->getExportFormat();
        if ($exportFormat == 'json') {
            $this->writeToJsonFile($sFileName, $aData);
        } elseif ($exportFormat == 'yaml') {
            $this->writeStringToFile($sFileName, Yaml::dump($aData, 5));
        }
    }

    /**
     * Returns a list of server identifiers. Do not export, as it can cause out-of-envorinment variables when imported.
     *
     * @return string[]
     */
    protected function _getNodeIdentifiers()
    {
        $aServersKeysAsFound = \oxDb::getDb(\oxDb::FETCH_MODE_ASSOC)->getAll(
            "SELECT `OXID`, `OXVARNAME` FROM `oxconfig` WHERE `OXVARNAME` LIKE ?;",
            ['aServersData%']
        );

        $aServersKeys = [];
        foreach ($aServersKeysAsFound as $aServersKey)
        {
            $aServersKeys[] = $aServersKey['OXVARNAME'];
        }

        return array_unique($aServersKeys);
    }

    /**
     * @param string $sFileName
     * @param array  $aData
     */
    protected function writeToJsonFile($sFileName, $aData)
    {
        $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $this->writeStringToFile($sFileName, json_encode($aData, $options));
    }

    /**
     * @param string $sFileName
     * @param string $sData
     *
     * @throws \RuntimeException
     */
    protected function writeStringToFile($sFileName, $sData)
    {
        $sMode = 'w';
        if ($sFileName && $sData) {
            $oFile = new \SplFileObject($sFileName, $sMode);
            $oFile->fwrite($sData);
        }
    }

    /**
     * @return array
     */
    public function getGlobalExcludedFields()
    {
        $aGlobalExcludeFields = array_merge(
            //$this->_getImFields(),
            $this->_getDefaultExcludeFields(),
            $this->aConfiguration['excludeFields'], //Custom exclude fields
            $this->aConfiguration['envFields'],
            $this->_getOllcFields(),
            $this->_getNodeIdentifiers()
        );

        return $aGlobalExcludeFields;
    }

    /**
     * Exclude fields that will be written when modules are activated.
     *
     * @return string[]
     */
    protected function _getDefaultExcludeFields()
    {
        return [
            'aModuleControllers',
            'aModuleExtensions'
        ];
    }

    /**
     * @param $aGlobalExcludeFields
     *
     * @return array
     */
    public function getCommonConfigurationValues($aGlobalExcludeFields)
    {
        $aReturn = $this->getConfigValues($aGlobalExcludeFields, false);

        return $aReturn;
    }

    /**
     * Fields relevant for OLC. Exporting them will cause offline errors.
     *
     * @return string[]
     */
    protected function _getOllcFields()
    {
        return [
            'iOlcSuccess',
            'sClusterId',
            'sOnlineLicenseCheckTime',
            'sOnlineLicenseNextCheckTime'
        ];
    }

    /**
     * @return array
     */
    public function getEnvironmentSpecificConfigurationValues()
    {
        $aReturn = $this->getConfigValues($this->aConfiguration['envFields'], true);

        return $aReturn;
    }

    /**
     * @param $aReturn
     */
    public function writeEnvironmentSpecificConfigurationValues($aReturn)
    {
        $this->writeDataToFileSeperatedByShop($this->getEnvironmentConfigDir(), $aReturn);
    }

    /**
     * @param $aShops
     */
    public function writeMetaConfigFile($aShops)
    {
        $aMetaConfigFile['shops']                 = $aShops;
        $aMetaConfigFile[$this->sNameForMetaData] = $this->aDefaultConfig[$this->sNameForMetaData];

        $this->writeDataToFile($this->getShopsConfigFileName(), $aMetaConfigFile);
    }

    private function varValueWithThemeDisplayInfo($sVarName, $mVarValue, $sVarType, $sVarConstraints, $sVarGrouping, $sVarPos)
    {
        if (!empty($sVarConstraints)||!empty($sVarPos)||!empty($sVarGrouping)) {
            $mVarValue = array('value' => $this->varValueWithTypeInfo($sVarName, $mVarValue, $sVarType));
            if(!empty($sVarConstraints)) { $mVarValue['constraints'] = $sVarConstraints; }
            if(!empty($sVarGrouping)) { $mVarValue['grouping'] = $sVarGrouping; }
            if(!empty($sVarPos)) { $mVarValue['pos'] = $sVarPos; }
        }
        return $mVarValue;
    }

    /**
     * Alternative to php's built in cast to bool.
     * @param mixed $mDefaultValue
     * @return bool
     */
    protected function convertToBool($mDefaultValue)
    {
        if ((is_string($mDefaultValue)) && ($mDefaultValue === 'false')) {
            $mDefaultValue = false;
        } else {
            $mDefaultValue = $mDefaultValue ? true : false;
        }
        return $mDefaultValue;
    }
}
