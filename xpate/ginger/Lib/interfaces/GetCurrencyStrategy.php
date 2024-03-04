<?php
namespace Lib\interfaces;

interface GetCurrencyStrategy extends BaseStrategy
{
    function getOrderCurrency(string $id_currency);
}