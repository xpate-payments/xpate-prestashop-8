<?php
namespace Lib\interfaces;

interface BuildClientStrategy extends BaseStrategy
{
    function gingerBuildClient($paymentMethod);
    function getTestAPIKey($paymentMethod);
}