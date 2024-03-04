<?php

namespace Lib\components;
use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;
use Lib\interfaces\BuildClientStrategy;


class GingerClientBuilder
{
    public static function gingerBuildClient($paymentMethod = '')
    {
        $buildClientStrategy = ComponentRegistry::get(BuildClientStrategy::class);
         return $buildClientStrategy->gingerBuildClient($paymentMethod);
    }
}