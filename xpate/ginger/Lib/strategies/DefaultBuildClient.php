<?php
namespace Lib\strategies;
use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;
use Lib\components\GingerPSPConfig;
use Lib\interfaces\BuildClientStrategy;

class DefaultBuildClient implements BuildClientStrategy
{
    function gingerBuildClient($paymentMethod)
    {
        $apiKey = $paymentMethod ? (new DefaultBuildClient)->getTestAPIKey($paymentMethod) : \Configuration::get('GINGER_API_KEY');
        return new Client(
            new ClientOptions(
                endpoint: GingerPSPConfig::GINGER_PSP_ENDPOINT,
                useBundle: \Configuration::get('GINGER_BUNDLE_CA') === 'on',
                apiKey: $apiKey
            )
        );
    }
    public function getTestAPIKey($paymentMethod)
    {
        if (\Configuration::get('GINGER_'.strtoupper($paymentMethod).'_TEST_API_KEY'))
        {
            return \Configuration::get('GINGER_'.strtoupper($paymentMethod).'_TEST_API_KEY');
        }

        return \Configuration::get('GINGER_API_KEY');
    }
}