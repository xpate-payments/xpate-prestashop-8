<?php

namespace Lib\components;
use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;


class GingerClientBuilder
{
    public static function gingerBuildClient($paymentMethod)
    {
        $apiKey = $paymentMethod ? self::getTestAPIKey($paymentMethod) : \Configuration::get('GINGER_API_KEY');
        return new Client(
            new ClientOptions(
                endpoint: GingerPSPConfig::GINGER_PSP_ENDPOINT,
                useBundle: \Configuration::get('GINGER_BUNDLE_CA') === 'on',
                apiKey: $apiKey
            )
        );
    }

    public static function getTestAPIKey($paymentMethod)
    {
        if (\Configuration::get('GINGER_'.strtoupper($paymentMethod).'_TEST_API_KEY'))
        {
            return \Configuration::get('GINGER_'.strtoupper($paymentMethod).'_TEST_API_KEY');
        }

        return \Configuration::get('GINGER_API_KEY');
    }
}
