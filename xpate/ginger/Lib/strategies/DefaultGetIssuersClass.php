<?php
namespace Lib\strategies;
use Lib\components\GingerClientBuilder;
use Lib\interfaces\GetIssuersStrategy;

class DefaultGetIssuersClass  implements GetIssuersStrategy
{
    function _getIssuers()
    {
        $client = GingerClientBuilder::gingerBuildClient();
        return $client->getIdealIssuers()->toArray();
    }
}