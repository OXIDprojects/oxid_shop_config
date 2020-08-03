<?php

return array(
    'dir'                           => getShopBasePath() . '/../configurations',
    'type'                          => 'yaml',
    'executeModuleActivationEvents' => true,
    //config fields that should never ever go to the config export
    //because they are generated or sensible in any way
    //TODO: document them
    'excludeFields'                 => array(
        'aServersData',
        'blEnableIntangibleProdAgreement',
        'blShowTSCODMessage',
        'blShowTSInternationalFeesMessage',
        'iOlcSuccess',
        'sBackTag',
        'sClusterId',
        'sOnlineLicenseCheckTime',
        'sOnlineLicenseNextCheckTime',
        'sParcelService',
        'blUseContentCaching',
        'iTimeToUpdatePrices',
        'OXSERIAL', // generated single serial number from all aSerials, will be generated during import
        //timestamp to check if cron jobs must be executed
        'iFailedOnlineCallsCount',
        //sometimes good to not exclude this to have value be restored to 0 on import, but on the other hand
        //bad to having this field be exported from vm where the firewall may block license check
        /* d3 */
        'd3RemoteServerCache',
        /* Oxsearch */
        'marmOxsearchImporterStatus'
        //status of the last transfer to elasticsearch. excluded because it is related to the cluster where the export was executed

        /* Project specific settings */

        /* /Project specific settings */

    ),
    //environment specific fields
    'envFields'                     => array(
        'aSerials', //oxid serial numbers. Must be different on live system.
        'sMallShopURL',
        'sMallSSLShopURL',
        'blCheckTemplates', //sets if templates should be recompililed on change good in develop env
        'aMemcachedServers',

        /* oxshops table */
        'OXPRODUCTIVE',
        'OXSMTP',
        'OXSMTPUSER',
        'OXINFOEMAIL',
        'OXORDEREMAIL',
        'OXOWNEREMAIL',
        /* END oxshops table */

        /* Paypal development settings */
        'blOEPayPalSandboxMode',
        'blPayPalLoggerEnabled',
        'sOEPayPalPassword',
        'sOEPayPalSignature',
        'sOEPayPalUserEmail',
        'sOEPayPalUsername',
        'sOEPayPalSandboxPassword',
        'sOEPayPalSandboxSignature',
        'sOEPayPalSandboxUserEmail',
        'sOEPayPalSandboxUsername',
        /* END Paypal development settings END */

        /* modul specific settings keep them even if you do not use them,
           so you do not have to get them again when you use one of them
        */

        /* oxsearch */
        'marm_oxsearch_config',
        /* END oxsearch specific settings */
        /* Factfinder */
        'swFF.authentication.password',
        'swFF.authentication.username',
        'swFF.context',
        /* END Factfinder END */

        /* contenido cms */
        'o2c_sUsername',
        'o2c_sUserPassword',
        'o2c_sSoapServerAddress',
    ),
    'env'                           => array(
        /* map other environments to existing ones when directory names do not match env. names.
           do NOT list every environment here, only aliases should be defined here
           default is 
           dir' => getShopBasePath() . '/modules/oxps/modulesconfig/configurations/$env'
        */

        /* map other environments to existing ones */
        'development'   => array(
            'dir' => getShopBasePath() . '/../configurations/development',
        ),
        'merge-request' => array(
            'dir' => getShopBasePath() . '/../configurations/integration',
        ),
        'testing'       => array(
            'dir' => getShopBasePath() . '/../configurations/testing',
        )
    )
);
