<?php

use Lib\components\GingerPlugin;
use Lib\components\GingerInstallTrait;
use Lib\interfaces\GingerCustomFieldsOnCheckout;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(\_PS_MODULE_DIR_ . 'xpate/ginger/vendor/autoload.php');

class xpateapplepay extends GingerPlugin implements GingerCustomFieldsOnCheckout
{
    use GingerInstallTrait;
    public function __construct()
    {
        $this->name = 'xpateapplepay';
	    $this->method_id = 'apple-pay';
        parent::__construct();
    }
}
