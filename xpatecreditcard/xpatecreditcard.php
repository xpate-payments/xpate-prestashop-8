<?php

use Lib\components\GingerConfigurableTrait;
use Lib\components\GingerPlugin;
use Lib\components\GingerInstallTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(\_PS_MODULE_DIR_ . 'xpate/ginger/vendor/autoload.php');

class xpatecreditcard extends GingerPlugin
{
    use GingerInstallTrait, GingerConfigurableTrait;

    public function __construct()
    {
        $this->name = 'xpatecreditcard';
	    $this->method_id = 'credit-card';
        parent::__construct();
    }
}
