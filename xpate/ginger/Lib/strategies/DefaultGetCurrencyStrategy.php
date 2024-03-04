<?php
namespace Lib\strategies;
use GingerPluginSdk\Properties\Currency;
use Lib\interfaces\GetCurrencyStrategy;

class DefaultGetCurrencyStrategy implements GetCurrencyStrategy
{

    function getOrderCurrency(string $id_currency)
    {
        $currencyOrder = new \Currency($id_currency);
        return new Currency($currencyOrder->iso_code);
    }
}