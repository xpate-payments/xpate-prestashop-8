<?php
namespace Lib\components;

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
}
