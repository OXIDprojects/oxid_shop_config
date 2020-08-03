<?php
/**
 * This file is part of OXID Console.
 *
 * OXID Console is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID Console is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID Console.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author        OXID Professional services
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2015
 */

namespace OxidProfessionalServices\OxidShopConfig\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use OxidProfessionalServices\OxidShopConfig\Core\ConfigImport;

class ImportCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this
            ->setName('config:import')
            ->setDescription('Import shop config')
            ->addOption(
                'env',
                'e',
                InputOption::VALUE_REQUIRED,
                "Environment to execute in"
            );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(ConfigImport::class)) {
            $output->writeLn('Config importer is not active trying to activate...');
            $oModuleInstaller = \oxRegistry::get('oxModuleInstaller');
            $oxModuleList = oxNew('oxModuleList');
            $oxModuleList->getModulesFromDir(\oxRegistry::getConfig()->getModulesDir());
            $aModules = $oxModuleList->getList();
            /** @var \oxModule $oModule */
            $oModule = $aModules['oxpsmodulesconfig'];
            $oModuleInstaller->activate($oModule);

            //workaround for issue in oxid see https://github.com/OXID-eSales/oxideshop_ce/pull/413
            $utilsObject = \oxUtilsObject::getInstance();
            $utilsObject->setModuleVar('aModuleFiles', null);
        }
        $oConfigImport = oxNew(ConfigImport::class, $output, $input);
        $oConfigImport->executeConsoleCommand();
    }

}
