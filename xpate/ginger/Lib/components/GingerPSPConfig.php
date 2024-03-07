<?php
namespace Lib\components;

use Lib\interfaces\BuildClientStrategy;
use Lib\interfaces\GetCurrencyStrategy;
use Lib\interfaces\GetIssuersStrategy;
use Lib\strategies\CustomGetCurrencyStrategy;
use Lib\strategies\DefaultBuildClient;
use Lib\strategies\CustomGetIssuersClass;
use Lib\strategies\DefaultGetCurrencyStrategy;
use Lib\strategies\DefaultGetIssuersClass;

class GingerPSPConfig
{

    const GINGER_PSP_LABELS = [
        'xpate' => 'Library',
        'apple-pay' => 'Apple Pay',
        'credit-card' => 'Credit/debit card',
        'google-pay' => 'Google Pay',
    ];

    const PLUGIN_NAME = 'xpate-online-prestashop-8.0';
    const PSP_LABEL = 'Xpate';
    const PSP_PREFIX = 'xpate';
    const GINGER_PSP_ENDPOINT = 'https://api.gateway.xpate.com';

    public static function registerStrategies(){
        ComponentRegistry::register(GetIssuersStrategy::class, new DefaultGetIssuersClass());
        ComponentRegistry::register(GetCurrencyStrategy::class,new DefaultGetCurrencyStrategy());
		ComponentRegistry::register(BuildClientStrategy::class,new DefaultBuildClient());
    }
}