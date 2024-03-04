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

class GingerBankConfig
{

    const GINGER_BANK_LABELS = [
        'xpate' => 'Library',
        'apple-pay' => 'Apple Pay',
        'credit-card' => 'Credit/debit card',
        'google-pay' => 'Google Pay',
    ];

    const PLUGIN_NAME = 'xpate-online-prestashop-8.0';
    const BANK_LABEL = 'Xpate';
    const BANK_PREFIX = 'xpate';
    const GINGER_BANK_ENDPOINT = 'https://api.gateway.xpate.com';

    public static function registerStrategies(){
        ComponentRegistry::register(GetIssuersStrategy::class, new DefaultGetIssuersClass());
        ComponentRegistry::register(GetCurrencyStrategy::class,new DefaultGetCurrencyStrategy());
		ComponentRegistry::register(BuildClientStrategy::class,new DefaultBuildClient());
    }
}