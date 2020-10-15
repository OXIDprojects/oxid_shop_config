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
use OxidEsales\Eshop\Application\Controller\Admin\ShopLicense;
use OxidEsales\Eshop\Core\Registry;

/**
 * Class ConfigImport
 * Implements functionality for the ImportCommand
 */
class ConfigImport extends CommandBase
{

    /**
     * @var \OxidEsales\Eshop\Core\Config $oConfig
     */
    protected $oConfig;

    /**
     * @var int The shop id to load the config for.
     */
    protected $sShopId;

    /**
     * @var array "name+module" => type
     * used to check if the imported config value type matches the stored type in the oxconfig table
     * if not the type must be overridden.
     * it helps to avoid unnecessary db rights on deployment
     */
    protected $storedVarTypes = [];

    protected $storedDisplayConfigHash = [];


    public function init()
    {
        parent::init();
        $db = DatabaseProvider::getDb();
        $hashValues = $db->getCol(
            "SELECT md5(CONCAT(oxcfgmodule,'#', oxcfgvarname,'#', oxgrouping,'#', oxvarconstraint,'#', oxpos))"
            . " FROM oxconfigdisplay"
        );
        $this->storedDisplayConfigHash = array_fill_keys($hashValues, true);
    }

    /*
     * executes all functionality which is necessary for a call of OXID console config:import
     *
     */
    public function executeConsoleCommand()
    {
        try {
            $this->init();
            // import environment specific config values

            $aMetaConfig = $this->readConfigValues($this->getShopsConfigFileName());
            $aShops = $aMetaConfig['shops'];
            $this->runShopConfigImportForAllShops($aShops);
            $this->generateOxserial();
            $this->output->writeLn("done");
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            $this->output->writeLn("Could not parse a YAML File.");
            $this->output->writeLn($e->getMessage());
            exit(1);
        } catch (\oxFileException $oEx) {
            $this->output->writeLn("Could not complete");
            $this->output->writeLn($oEx->getMessage());
            exit(2);
        } catch (\RuntimeException $e) {
            $this->output->writeLn("Could not complete.");
            $this->output->writeLn($e->getMessage());
            $this->output->writeLn($e->getTraceAsString());
            exit(3);
        }
    }

    /**
     * runShopConfigImportForOneShop
     *
     * @param $sShop
     * @param $sRelativeFileName
     *
     * @throws \Exception
     */
    protected function runShopConfigImportForOneShop($sShop, $sRelativeFileName)
    {

        $sFileName = $this->getConfigDir() . $sRelativeFileName;
        $aResult = $this->readConfigValues($sFileName);

        $aResult = $this->mergeConfig($this->aDefaultConfig, $aResult);

        if ($this->sEnv) {
            $sEnvDirName = $this->getEnvironmentConfigDir();
            $sFileName = $sEnvDirName . $sRelativeFileName;
            $aEnvConfig = $this->readConfigValues($sFileName);
            $aResult = $this->mergeConfig($aResult, $aEnvConfig);
        }

        $this->output->writeLn("Importing config for shop $sShop");

        $this->importConfigValues($aResult);
    }

    /**
     * Generates OXSERIAL stored in oxshops table from aSerials (from oxconfig table)
     */
    protected function generateOxserial()
    {
        $licence = oxNew(ShopLicense::class);
        Registry::getSession()->setVariable('malladmin', true);

        $licence->updateShopSerial();

        $this->output->writeLn('generated OXSERIAL');
    }

    /**
     * merge two config arrays
     *
     * @param $aBase
     * @param $aOverride
     *
     * @return
     */
    protected function mergeConfig($aBase, $aOverride)
    {
        foreach ($aOverride as $key => $mOverriderValue) {
            if (is_array($mOverriderValue)) {
                $aBaseValue = isset($aBase[$key]) ? $aBase[$key] : null;
                if ($aBaseValue) {
                    if (is_array($aBaseValue)) {
                        $mOverriderValue = array_merge($aBaseValue, $mOverriderValue);
                    } else {
                        $this->output->writeLn(
                            "ERROR: Ignoring corrupted common config value '$key':'$aBaseValue' for shop "
                            . $this->sShopId
                        );
                    }
                }
            } else {
                $this->output->writeLn(
                    "ERROR: Skipping corrupted config value '$key':'$mOverriderValue' for shop " . $this->sShopId
                );
                continue;
            }
            $aBase[$key] = $mOverriderValue;
        }

        return $aBase;
    }

    /**
     * @param array $aConfigValues
     */
    protected function importShopsConfig($aConfigValues)
    {
        /**
         * @var \oxShop $oShop
         */
        $oShop = oxNew("oxshop");
        $sShopId = $this->sShopId;
        if (!$oShop->load($sShopId)) {
            $this->output->writeLn("[WARN] Creating new shop $sShopId");
            $oShop->setId($sShopId);
            // The method below was using the Config class defined in the old oxid console.
            //$oConfig = ShopConfig::get(1);
            $oConfig = oxNew(\OxidEsales\Eshop\Core\Config::class);
            $oConfig->setShopId('1');
            $oConfig->saveShopConfVar(
                'arr',
                'aModules',
                array(),
                $sShopId,
                ""
            );
        }
        $oShop->setShopId($sShopId);
        $aOxShopSettings = $aConfigValues['oxshops'];
        if ($aOxShopSettings) {
            $db = \oxDb::getDb(\oxDb::FETCH_MODE_ASSOC);
            $old = $db->select("select * from oxshops where oxid = ?", [$sShopId])->fetchAll();
            $old = $old ? $old[0] : [];
            $diff = array_diff_assoc($aOxShopSettings, $old);
            if ($diff) {
                $oShop->assign($aOxShopSettings);
                //fake active shopid to allow derived update
                $oShop->getConfig()->setShopId($sShopId);
                $oShop->save();
                for ($i = 1; $i <= 3; $i++) {
                    $oShop->setLanguage($i);
                    foreach ($aOxShopSettings as $sVarName => $mVarValue) {
                        $iPosLastChar = strlen($sVarName) - 1;
                        $iPosUnderscore = $iPosLastChar - 1;
                        if ($sVarName[$iPosUnderscore] == '_' && $sVarName[$iPosLastChar] == $i) {
                            $sFiledName = substr($sVarName, 0, strlen($sVarName) - 2);
                            $aOxShopSettings[$sFiledName] = $mVarValue;
                        }
                    }
                    $oShop->assign($aOxShopSettings);
                    $oShop->save();
                }
            }
        }
    }

    /*
     * @param string $sShopId
     * @param array $aConfigValues
     * @param bool $blRestoreModuleDefaults
     */
    protected function importConfigValues($aConfigValues)
    {
        $sShopId = $this->sShopId;
        $this->importShopsConfig($aConfigValues);
        // The method below gets uses a method defined in the old Oxid console's Config and cannot be used.
        // $oConfig = ShopConfig::get($sShopId);
        $oConfig = oxNew(\OxidEsales\Eshop\Core\Config::class);
        $oConfig->setShopId($sShopId);
        $this->oConfig = $oConfig;
        Registry::set('oxConfig', $oConfig);
        //the oxutilsobject holds the shop id indirectly within the shopid calculator
        //and so will cause reading the wrong cache and so cause errors during the fix states
        $_POST['shp'] = $sShopId;
        //just setting the correct shopId on this object because it defaults to one loaded by config init.
        //doing so does not have any known effect.
        $shop = $oConfig->getActiveShop();
        $shop->setShopId($sShopId);
        $oConfig->setShopId($sShopId);
        //set the global config object in oxid 6.2
        $shop->setConfig($oConfig);
        //we need a fresh instance here because
        //shopId calculator is private
        $freshUtilsObject = new \OxidEsales\Eshop\Core\UtilsObject();
        Registry::set(\OxidEsales\Eshop\Core\UtilsObject::class, $freshUtilsObject);
        //clear oxnew cache that may hold objects like Shop class from the first run
        $freshUtilsObject->resetInstanceCache();

        $ouo = Registry::get(\OxidEsales\Eshop\Core\UtilsObject::class);
        if (
            $oConfig->getShopId() != $sShopId ||
            $oConfig->getActiveShop()->getShopId() != $sShopId ||
            $ouo->getShopId() != $sShopId
        ) {
            throw new \Exception(
                "ShopId was not set correctly, this means shop internal have changed and import must be adapted"
            );
        }


        $this->restoreGeneralShopSettings($aConfigValues);

        $this->importThemeConfig($aConfigValues['theme']);

    }

    protected function getConfigValue($aConfigValues, $name)
    {
        $TypeAndValue = $aConfigValues[$this->sNameForGeneralShopSettings][$name];
        $TypeAndValue = $this->getTypeAndValue($name, $TypeAndValue);
        $value = $TypeAndValue[1];
        return $value;
    }



    protected function getTypeAndValue($sVarName, $mVarValue)
    {
        $specialTypes = ['str', 'bool', 'arr', 'aarr', 'select', 'string', 'int', 'num'];
        if ($this->isAssocArray($mVarValue)) {
            /*
             * We need to cover multiple sort of values here, the easiest one is just an assoc-array
             * which will be saved as a 'aarr'.
             * But we also need to cover some special config types like these:
             *    blPsBasketReservationEnabled:
             *      str: '0'
             *    blPsLoginEnabled:
             *      str: '0'
             * This looks like an assoc-array, but in reality it is just a boolean flag
             * which must be saved with the type 'str' (WAT?)
             */
            $sVarType = 'aarr';
            if (count($mVarValue) === 1 && in_array(array_keys($mVarValue)[0], $specialTypes, true)) {
                $sVarType = array_keys($mVarValue)[0];
                $mVarValue = array_values($mVarValue)[0];
            }
        } elseif (is_array($mVarValue)) {
            $sVarType = 'arr';
        } elseif (is_bool($mVarValue)) {
            $sVarType = 'bool';
        } else {
            //deprecated check for 'bl'
            if (substr($sVarName, 0, 2) === "bl") {
                $sVarType = 'bool';
            } else {
                $sVarType = 'str';
            }
        }

        return array($sVarType, $mVarValue);
    }

    protected function getStoredVarTypes()
    {
        $db = \oxDb::getDb(\oxDb::FETCH_MODE_ASSOC);
        $sQ = "select CONCAT(oxvarname,'+',oxmodule) as mapkey, oxvartype from oxconfig where oxshopid = ?";
        $allRows = $db->getAll($sQ, [$this->sShopId]);
        $map = [];
        foreach ($allRows as $row) {
            $map[$row['mapkey']] = $row['oxvartype'];
        }
        return $map;
    }

    public function getShopConfType($sVarName, $sSectionModule)
    {

        $cachekey = $sVarName . '+' . $sSectionModule;
        return isset($this->storedVarTypes[$cachekey]) ? $this->storedVarTypes[$cachekey] : null;
    }


    protected function saveShopVarWithTypeInfo($sVarName, $mVarValue, $sSectionModule)
    {
        list($sVarType, $mVarValue) = $this->getTypeAndValue($sVarName, $mVarValue);
        $this->saveShopVar($sVarName, $mVarValue, $sSectionModule, $sVarType);
    }


    protected function saveShopVar($sVarName, $mVarValue, $sSectionModule, $sVarType)
    {
        $sShopId = $this->sShopId;
        $oConfig = $this->oConfig;

        if ($sShopId != 1) {
            $aOnlyMainShopVars = array_fill_keys(['blMallUsers', 'aSerials', 'IMD', 'IMA', 'IMS'], true);
            if ($aOnlyMainShopVars[$sVarName]) {
                return;
            }
        }

        $oConfig->saveShopConfVar(
            $sVarType,
            $sVarName,
            $mVarValue,
            $sShopId,
            $sSectionModule
        );
    }

    protected function isAssocArray($arr)
    {
        return is_array($arr) && (array_keys($arr) !== range(0, count($arr) - 1));
    }

    /**
     * @param $aSectionData
     * @param $sShopId
     * @param $oConfig
     */
    protected function importThemeConfig($aThemes)
    {
        if ($aThemes == null) {
            return;
        }
        $parentTheme = $this->oConfig->getConfigParam('sTheme');
        $theme = $this->oConfig->getConfigParam('sCustomTheme');
        foreach ($aThemes as $sThemeId => $aSettings) {
            if ($sThemeId != $parentTheme && $theme != $sThemeId) {
                $this->output->writeln("Theme $sThemeId from import from config file ignored because it is not active");
                continue;
            }
            $sSectionModule = "theme:$sThemeId";
            foreach ($aSettings as $sVarName => $mVarValue) {
                if (isset($mVarValue['value'])) {
                    $this->saveShopVarWithTypeInfo($sVarName, $mVarValue['value'], $sSectionModule);
                    $this->saveThemeDisplayVars($sVarName, $mVarValue, $sSectionModule);
                } else {
                    $this->saveShopVarWithTypeInfo($sVarName, $mVarValue, $sSectionModule);
                }
            }
        }
    }

    /**
     * @param $aShops
     */
    protected function runShopConfigImportForAllShops($aShops)
    {
        foreach ($aShops as $sShop => $sFileName) {
            if (!$this->allShops && !in_array($sShop, $this->shops)) {
                continue;
            }
            $this->sShopId = $sShop;
            $this->storedVarTypes = $this->getStoredVarTypes();
            $this->runShopConfigImportForOneShop($sShop, $sFileName);
        }
    }

    /**
     * @param $aConfigValues
     * @return null
     */
    protected function restoreGeneralShopSettings($aConfigValues)
    {
        $aGeneralSettings = $aConfigValues[$this->sNameForGeneralShopSettings];
        $sSectionModule = '';
        foreach ($aGeneralSettings as $sVarName => $mTypedVarValue) {
            $this->saveShopVarWithTypeInfo($sVarName, $mTypedVarValue, $sSectionModule);
        }
    }

    protected function saveThemeDisplayVars($sVarName, $mVarValue, $sModule)
    {
        $oDb = \oxDb::getDb();
        $sModuleQuoted = $oDb->quote($sModule);
        $sVarNameQuoted = $oDb->quote($sVarName);
        $constraints = isset($mVarValue['constraints']) ? $mVarValue['constraints'] : null;
        $sVarConstraintsQuoted = isset($constraints) ? $oDb->quote($constraints) : '\'\'';
        $grouping = $mVarValue['grouping'];
        $sVarGroupingQuoted = isset($grouping) ? $oDb->quote($grouping) : '\'\'';
        $pos = isset($mVarValue['pos']) ? $mVarValue['pos'] : null;
        $sVarPosQuoted = isset($pos) ? $oDb->quote($pos) : '\'\'';

        $sNewOXIDdQuoted = $oDb->quote(\oxUtilsObject::getInstance()->generateUID());

        $hash = md5($sModule . '#' . $sVarName . '#' .  $grouping . '#' . $constraints . '#' . $pos);
        if (isset($this->storedDisplayConfigHash[$hash])) {
            return;
        }

        $sQ = "delete from oxconfigdisplay WHERE OXCFGVARNAME = $sVarNameQuoted and OXCFGMODULE = $sModuleQuoted";
        $oDb->execute($sQ);

        $sQ = "insert into oxconfigdisplay (oxid, oxcfgmodule, oxcfgvarname, oxgrouping, oxvarconstraint, oxpos )
               values($sNewOXIDdQuoted, $sModuleQuoted, $sVarNameQuoted, $sVarGroupingQuoted, $sVarConstraintsQuoted,
               $sVarPosQuoted)";
        $oDb->execute($sQ);
    }
}
