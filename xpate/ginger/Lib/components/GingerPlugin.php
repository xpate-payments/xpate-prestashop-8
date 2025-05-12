<?php
namespace Lib\components;

use Address;
use Exception;
use GingerPluginSdk\Collections\Transactions;
use GingerPluginSdk\Entities\Order;
use GingerPluginSdk\Properties\Amount;
use GingerPluginSdk\Properties\Currency;
use Lib\interfaces\GingerCapturable;
use Lib\interfaces\GingerCountryValidation;
use Lib\interfaces\GingerCustomFieldsOnCheckout;
use Lib\interfaces\GingerIdentificationPay;
use Lib\interfaces\GingerIPValidation;
use Model\Ginger;
use Model\GingerGateway;
use GingerPluginSdk\Exceptions\EmptyApiKeyException;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(\_PS_MODULE_DIR_ . 'xpate/ginger/vendor/autoload.php');

class GingerPlugin extends \PaymentModule
{

    protected $orderBuilder;
    protected $gingerClient;
    public $method_id;
    protected $extra_mail_vars;
    protected $_html = '';

    public function __construct()
    {
        $this->label = $this->trans(GingerPSPConfig::PSP_LABEL, [], 'Modules.Xpate.Admin');
        $this->method_name = $this->trans(GingerPSPConfig::GINGER_PSP_LABELS[$this->method_id], [], 'Modules.Xpate.Admin');

        $this->displayName = $this->trans('%label% %method%', ['%label%' => $this->label, '%method%'=>$this->method_name], 'Modules.Xpate.Admin');
        $this->description = $this->trans('Accept payments for your products using %method%', ['%method%'=>$this->method_name], 'Modules.Xpate.Admin');
        $this->tab = 'payments_gateways';
        $this->version = "1.0.1";
        $this->author = 'Ginger Payments';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        parent::__construct();
		
        try {
            $this->gingerClient = GingerClientBuilder::gingerBuildClient($this->method_id);
        }catch (EmptyApiKeyException $exception) {
            $this->warning = $exception->getMessage();
        }

        $this->confirmUninstall = $this->trans('Are you sure about removing these details?',[], 'Modules.Xpate.Admin');



        if (!count(\Currency::checkPaymentCurrencies($this->id)))
        {
            $this->warning = $this->trans('No currency has been set for this module.',[], 'Modules.Xpate.Admin');
        }
    }
    public function getGingerClient()
    {
        return $this->gingerClient;
    }

    public function install()
    {
        if (!parent::install()) return false;

        if ($this->name == GingerPSPConfig::PSP_PREFIX)
        {
            if (!$this->createTables()) return false; //Create table in db

            if (!$this->createOrderState()) return false;

            /**
             * Hook for partial refund
             * TODO: PLUG-856: the hook doesn't work on prestashop version 1.7.6.8, but works on version 1.7.7.6 (tested in docker)
             */
            if (!$this->registerHook('OrderSlip')) return false;

            return true;
        }

        if (!$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn'))
        {
            return false;
        }

        if($this instanceof GingerCustomFieldsOnCheckout)
        {
            if (!$this->registerHook('header')) return false;
        }

        if (!$this->registerHook('actionOrderStatusUpdate')) return false;

        if($this instanceof GingerCountryValidation)
        {
            \Configuration::updateValue('GINGER_'.strtoupper(str_replace('-','',$this->method_id)).'_COUNTRY_ACCESS', trim('NL, BE'));
        }

        return true;
    }

    public function uninstall()
    {

        if (!parent::uninstall())
        {
            return false;
        }

        if ($this->name == GingerPSPConfig::PSP_PREFIX)
        {
            if (!\Configuration::deleteByName('GINGER_API_KEY')) return false;

            return true;

        }

        $templateForVariable =  'GINGER_'.strtoupper(str_replace('-','',$this->method_id));

        if($this instanceof GingerIPValidation)
        {
            \Configuration::deleteByName($templateForVariable.'_SHOW_FOR_IP');
        }

        if($this instanceof GingerCountryValidation)
        {
            \Configuration::deleteByName($templateForVariable.'_COUNTRY_ACCESS');
        }

        return true;
    }


    /**
     * @param \Cart $cart
     * @return bool
     */
    protected function checkCurrency(\Cart $cart)
    {
        $currencyOrder = new \Currency($cart->id_currency);
        $currenciesModule = $this->getCurrency($cart->id_currency);

        if (is_array($currenciesModule))
        {
            foreach ($currenciesModule as $currencyModule)
            {
                if ($currencyOrder->id == $currencyModule['id_currency'])
                {

                    return $this->gingerClient->checkAvailabilityForPaymentMethodUsingCurrency($this->method_id,new Currency($currencyOrder->iso_code));
                }
            }
        }

        return false;
    }

    /**
     * update order with presta order id
     *
     * @param $GingerOrderId
     * @param $PSOrderId
     * @param $amount
     */
    public function updateGingerOrder($GingerOrderId, $PSOrderId, $amount)
    {
        $order = $this->gingerClient->getOrder($GingerOrderId);
        $orderNext = new Order(
            currency: $order->getOrderLines()->getLine()->getCurrency(),
            amount: new Amount($amount),
            customer: $order->getCustomer(),
            id: $GingerOrderId,
            merchantOrderId: (string) $PSOrderId
        );
        $this->gingerClient->updateOrder($orderNext);
    }

    public function hookPaymentOptions($params)
    {

        if (!$this->active)
        {
            return;
        }

        if (!$this->checkCurrency($params['cart']))
        {
            return;
        }


        if($this instanceof GingerIPValidation)
        {
            if (!$this->validateIP()) return;
        }



        $this->context->smarty->assign(
            array_filter([
                'this_path' => $this->_path,
                'this_path_bw' => $this->_path,
                'this_path_ssl' => \Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            ])
        );

        $paymentOption = new PaymentOption;
        $paymentOption->setCallToActionText($this->trans('Pay by %label% %method%',['%label%'=>$this->label,'%method%'=>$this->method_name], 'Modules.Xpate.Admin'));
        $paymentOption->setLogo(\Media::getMediaPath(__PS_BASE_URI__.'modules/' .$this->name. '/'.$this->name.'.svg'));
        $paymentOption->setAction($this->context->link->getModuleLink($this->name, 'payment'));
        $paymentOption->setModuleName($this->name);

        if($this instanceof GingerCountryValidation)
        {
            if (!$this->validateCountry($params['cart']->id_address_invoice))
            {
                return;
            }

            $userCountry = $this->getUserCountryFromAddressId($params['cart']->id_address_invoice);
            $this->context->smarty->assign(
                'terms_and_condition_url',
                (strtoupper($userCountry) === static::BE_ISO_CODE) ? static::TERMS_CONDITION_URL_BE : static::TERMS_CONDITION_URL_NL
            );
        }

        if($this instanceof GingerCustomFieldsOnCheckout)
        {
            $paymentOption->setForm($this->context->smarty->fetch('module:' . $this->name . '/views/templates/hook/payment.tpl'));
        }

        return [$paymentOption];

    }

    public function execPayment($cart, $locale = '')
    {
        try {
            $this->orderBuilder = new GingerOrderBuilder($this, $cart, $locale);
            $gingerOrder = $this->gingerClient->sendOrder($this->orderBuilder ->getBuiltOrder());
        } catch (Exception $exception) {
            return \Tools::displayError($exception->getMessage());
        }

        if ($gingerOrder->getStatus() == 'error')
        {
            return \Tools::displayError($gingerOrder->getCurrentTransaction()->getCustomerMessage()->get());
        }

        if (!$gingerOrder->getId())
        {
            return \Tools::displayError("Error: Response did not include id!");
        }


        if ($this instanceof GingerIdentificationPay)
        {
            $bankReference = $gingerOrder->getCurrentTransaction() ? $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->toArray()['reference'] : null;

            $this->saveGingerOrderId($gingerOrder, $cart->id, $this->context->customer->secure_key, $this->name, $this->currentOrder, $bankReference);
            $this->sendPrivateMessage($bankReference);
            \Tools::redirect($this->orderBuilder->getReturnURL($gingerOrder->getId()->get()));

        }

        $this->saveGingerOrderId($gingerOrder, $cart->id, $this->context->customer->secure_key, $this->name);
        $pay_url = $gingerOrder->getCurrentTransaction()->getPaymentUrl()->get();
        if (!$pay_url)
        {
            return \Tools::displayError("Error: Response did not include payment url!");
        }

        \Tools::redirect($pay_url);
    }

    /**
     *
     * @param Order $response
     * @param int $cartId
     * @param type $customerSecureKey
     * @param string $type
     */
    protected function saveGingerOrderId($response, $cartId, $customerSecureKey, $type, $currentOrder = null, $reference = null)
    {
        $ginger = new Ginger();
        $ginger->setGingerOrderId($response->getId()->get())
            ->setIdCart($cartId)
            ->setKey($customerSecureKey)
            ->setPaymentMethod($type)
            ->setIdOrder($currentOrder)
            ->setReference($reference);
        (new GingerGateway(\Db::getInstance()))
            ->save($ginger);
    }

    /**
     * fetch order from db
     *
     * @param int $cartId
     * @return array
     */
    protected function getOrderFromDB($cartId)
    {
        return (new GingerGateway(\Db::getInstance()))->getByCartId($cartId);
    }

    /**
     * update order id
     *
     * @param type $cartId
     * @param type $orderId
     * @return type
     */
    public function updateOrderId($cartId, $orderId)
    {
        return (new GingerGateway(\Db::getInstance()))->update($cartId, $orderId);
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
        {
            return;
        }

        $this->orderBuilder = new GingerOrderBuilder($this, $params['order']);
        $ginger = $this->getOrderFromDB($params['order']->id_cart);
        $this->updateGingerOrder($ginger->getGingerOrderId(), $params['order']->id, $params['order']->total_paid*100);

        if($this instanceof GingerIdentificationPay)
        {
            $gingerOrder = $this->gingerClient->getOrder($ginger->getGingerOrderId());
            $gingerOrderIBAN = $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->toArray()['creditor_iban'];
            $gingerOrderBIC = $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->toArray()['creditor_bic'];
            $gingerOrderHolderName = $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->toArray()['creditor_account_holder_name'];
            $gingerOrderHolderCity = $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->toArray()['creditor_account_holder_city'];

            $this->context->smarty->assign(array(
                'total_to_pay' => \Tools::displayPrice($params['order']->getOrdersTotalPaid(), new \Currency($params['order']->id_currency), false),
                'gingerbanktransferIBAN' => $gingerOrderIBAN,
                'gingerbanktransferAddress' => $gingerOrderHolderCity,
                'gingerbanktransferOwner' => $gingerOrderHolderName,
                'gingerbanktransferBIC' => $gingerOrderBIC,
                'status' => 'ok',
                'reference' => $ginger->getReference(),
                'shop_name' => strval(\Configuration::get('PS_SHOP_NAME'))
            ));
        }

        return $this->fetch('module:'.$this->name.'/views/templates/hook/payment_return.tpl');
    }

    public function hookHeader()
    {
        $this->context->controller->addCss($this->_path . 'views/css/'.$this->method_id.'_form.css');
        $this->context->controller->addJS($this->_path . 'views/js/'.$this->method_id.'_form.js');
    }


    public function validateIP()
    {
        $ipFromConfiguration = \Configuration::get('GINGER_'.strtoupper(str_replace('-','',$this->method_id)).'_SHOW_FOR_IP'); //ex. klarna-pay-later GINGER_KLARNAPAYLATER_SHOW_FOR_IP
        if (strlen($ipFromConfiguration))
        {
            $ipWhiteList = array_map('trim', explode(",", $ipFromConfiguration));

            if (!in_array(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP), $ipWhiteList))
            {
                return false;
            }
        }
        return true;
    }


    public function hookActionOrderStatusUpdate($params)
    {
        $newStatus = (int) $params['newOrderStatus']->id;
        $orderId = (int) $params['id_order'];

        $ginger = (new GingerGateway(\Db::getInstance()))->getByOrderId($orderId);
        $gingerOrderID = $ginger->getGingerOrderId();

        if ($this instanceof GingerCapturable && $newStatus === (int) \Configuration::get('PS_OS_SHIPPING')){
            try {
                $gingerOrder = $this->gingerClient->getOrder($gingerOrderID);

                if (!$gingerOrder->getCurrentTransaction()->isCapturable()) return true; //order is not able to be captured
                if (in_array('has-captures',$gingerOrder->getFlags()->toArray())) return true; //order is already captured

                $this->gingerClient->captureOrderTransaction($gingerOrderID);
            } catch (Exception $exception) {
                \Tools::displayError($exception->getMessage());
                return false;
            }
            return true;
        }

        if ($newStatus === (int) \Configuration::get('PS_OS_PAYMENT'))
        {
            if (!\Configuration::get('GINGER_CREDITCARD_CAPTURE_MANUAL')) {
                return true;
            }
            try {
                $gingerOrder = $this->gingerClient->getOrder($gingerOrderID);

                if (!$gingerOrder->getCurrentTransaction()->isCapturable()) return true; //order is not able to be captured
                if ($gingerOrder->getFlags() && in_array('has-captures', (array) $gingerOrder->getFlags()->get())) return true; //order is already captured

                $this->gingerClient->captureOrderTransaction($gingerOrderID);
            }catch (Exception $exception) {
                \Tools::displayError($exception->getMessage());
                return false;
            }

        }elseif ($newStatus === (int) \Configuration::get('PS_OS_CANCELED')){
            if (!\Configuration::get('GINGER_CREDITCARD_CAPTURE_MANUAL')) {
                return true;
            }
            try {
                $gingerOrder = $this->gingerClient->getOrder($gingerOrderID);
                if (!$gingerOrder->getStatus()->get() == 'completed') return true;

                $transaction = $gingerOrder->getCurrentTransaction()->toArray();

                if (!isset($transaction['transaction_type']) || !$transaction['transaction_type'] == 'authorization') return true;

                if (in_array('has-captures', (array) $gingerOrder->getFlags()?->get()) || in_array('has-voids', (array) $gingerOrder->getFlags()?->get())) return true;

                $apiClient = $this->gingerClient->getApiClient();
                $apiClient->send('POST', sprintf('/orders/%s/transactions/%s/voids/amount', $gingerOrderID, $gingerOrder->getCurrentTransaction()->getId()->get()),
                    ['amount' => $gingerOrder->getAmount()->get(), 'description' => sprintf(
                        "Void %s of the full %s on order %s ",
                        $gingerOrder->getAmount()->get(), $gingerOrder->getAmount()->get(), $gingerOrder->getMerchantOrderId()->get()
                    )]);
            }catch (Exception $exception) {
                \Tools::displayError($exception->getMessage());
                return false;
            }
        }
        return true;
    }

    public function getUserCountryFromAddressID($addressID)
    {
        $prestaShopAddress = new \Address((int) $addressID);
        $country = new \Country(intval($prestaShopAddress->id_country));
        return strtoupper($country->iso_code);
    }

    public function validateCountry($addressID)
    {
        $userCountry = $this->getUserCountryFromAddressID($addressID);

        $countriesFromConfiguration = \Configuration::get('GINGER_'.strtoupper(str_replace('-','',$this->method_id)).'_COUNTRY_ACCESS');
        if (!$countriesFromConfiguration)
        {
            return true;
        }

        $countryList = array_map('trim', (explode(",", $countriesFromConfiguration)));
        if (!in_array($userCountry, $countryList))
        {
            return false;
        }

        if (!in_array($userCountry, $this->allowedLocales))
        {
            return false;
        }

        return true;
    }

    /**
     * Refund function
     */
    public function productRefund($order, $partialRefund, $id_cart)
    {
        $query = \Db::getInstance()->getRow("SELECT ginger_order_id FROM `" . _DB_PREFIX_ . GingerPSPConfig::PSP_PREFIX."` WHERE `id_cart` = " . $id_cart);
        if (!$query || !isset($query['ginger_order_id'])) return false;

        $gingerOrderID = $query['ginger_order_id'];
        try {
            $this->gingerClient->refundOrder($gingerOrderID, $partialRefund);

            $newHistory = new \OrderHistory();
            $newHistory->id_order = (int)$order->id;
            $newHistory->changeIdOrderState(\Configuration::get('PS_OS_REFUND'), $order, true);
            if (!$newHistory->add()) return false;
            return true;
        } catch (Exception $e) {
            \PrestaShopLogger::addLog('Ginger refund error: ' . $e->getMessage() . ' for order ID ' . $order->id, 3);
            return false;
        }
    }

    /**
     * @param $bankReference
     */
    public function sendPrivateMessage($bankReference)
    {
        $new_message = new \Message();
        $new_message->message = $this->trans('%label% %method% Reference: ',['%label'=> $this->label, '%method%' => $this->method_name],'Modules.Xpate.Admin') . $bankReference;
        $new_message->id_order = $this->currentOrder;
        $new_message->private = 1;
        $new_message->add();
    }

    public function createTables()
    {
		$db = \Db::getInstance();

        $dropQuery = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.GingerPSPConfig::PSP_PREFIX.'`';
        $db->execute($dropQuery);
		$createQuery = '
    CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.GingerPSPConfig::PSP_PREFIX.'` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_cart` int(11) DEFAULT NULL,
        `id_order` int(11) DEFAULT NULL,
        `key` varchar(64) NOT NULL,
        `ginger_order_id` varchar(36) NOT NULL,
        `payment_method` text,
        `reference` varchar(32) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `id_order` (`id_cart`),
        KEY `ginger_order_id` (`ginger_order_id`)
    ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        return $db->execute($createQuery);

    }

    public function createOrderState()
    {
        if (!\Configuration::get('GINGER_AUTHORIZED'))
        {
            $orderState = new \OrderState();
            $orderState->send_email = false;
            $orderState->color = '#3498D8';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = false;
            $orderState->invoice = false;
            $orderState->paid = false;


            $translations = [
                'en' => 'Authorized',
                'de' => 'Autorisiert',
                'fr' => 'Autorisé',
                'es' => 'Autorizado',
                'it' => 'Autorizzato',
                'nl' => 'Geautoriseerd',
                'pt' => 'Autorizado',
                'sv' => 'Auktoriserad',
                'da' => 'Autoriseret',
                'no' => 'Autorisert',
                'fi' => 'Valtuutettu',
                'pl' => 'Autoryzowany',
                'cs' => 'Autorizované stránky',
                'sk' => 'Autorizovaná stránka',
                'hu' => 'Engedélyezett',
                'ro' => 'Autorizat',
                'el' => 'Εξουσιοδοτημένο',
                'bg' => 'Оторизиран',
                'lv' => 'Autorizēts',
                'lt' => 'Įgaliotas',
                'et' => 'Lubatud',
            ];

            $languages = \Language::getLanguages(false);
            foreach ($languages as $lang) {
                $iso = $lang['iso_code'];
                $orderState->name[$lang['id_lang']] = $translations[$iso] ?? 'Authorized';
            }

            if (!$orderState->add()) return false;

            \Configuration::updateValue('GINGER_AUTHORIZED', (int)$orderState->id);
        }
        return true;
    }


    /**
     * Hook for partial refund
     */
    public function hookOrderSlip($params)
    {
        $order = new \Order($params['order']->id);
        $partialRefund = current($params['productList'])['total_refunded_tax_incl'];
        $this->productRefund(
            $order,
            new Amount($partialRefund * 100),
            $params['order']->id_cart);
    }

    /**
     * @param $cart
     * @return mixed
     * Uses for Order Lines, but can't be placed in OrderBuilder cause $this->context is protected field in each payment class
     */
    public function getShippingTaxRate($cart)
    {
        $carrier = new \Carrier((int) $cart->id_carrier, (int) $this->context->cart->id_lang);

        return $carrier->getTaxesRate(
            new Address((int) $this->context->cart->{\Configuration::get('PS_TAX_ADDRESS_TYPE')})
        );
    }

    /**
     * @param $product
     * @return mixed
     * Uses for Order Lines, but can't be placed in OrderBuilder cause $this->context is protected field in each payment class
     */
    public function getProductCoverImage($product)
    {
        $productCover = \Product::getCover($product['id_product']);

        if ($productCover)
        {
            $link_rewrite = $product['link_rewrite'] ?? \Db::getInstance()->getValue('SELECT link_rewrite FROM '._DB_PREFIX_.'product_lang WHERE id_product = '.(int) $product['id_product']);
            return $this->context->link->getImageLink($link_rewrite, $productCover['id_image']);
        }
    }

    /**
     * @param $product
     * @return string|null
     * Uses for Order Lines, but can't be placed in OrderBuilder cause $this->context is protected field in each payment class
     */
    public function getProductURL($product)
    {
        $productURL = $this->context->link->getProductLink($product);
        return strlen($productURL) > 0 ? $productURL : null;
    }
}


