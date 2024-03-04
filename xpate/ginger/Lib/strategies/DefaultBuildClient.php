<?php
namespace Lib\strategies;
use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;
use Lib\components\GingerBankConfig;
use Lib\interfaces\BuildClientStrategy;

class DefaultBuildClient implements BuildClientStrategy
{
    function gingerBuildClient($paymentMethod)
    {
        $apiKey = $paymentMethod ? (new DefaultBuildClient)->getTestAPIKey($paymentMethod) : \Configuration::get('GINGER_API_KEY');
        return new Client(
            new ClientOptions(
                endpoint: GingerBankConfig::GINGER_BANK_ENDPOINT,
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