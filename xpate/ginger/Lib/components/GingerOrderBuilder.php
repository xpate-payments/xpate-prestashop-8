<?php

namespace Lib\components;

use GingerPluginSdk\Collections\AdditionalAddresses;
use GingerPluginSdk\Collections\OrderLines;
use GingerPluginSdk\Collections\PhoneNumbers;
use GingerPluginSdk\Collections\Transactions;
use GingerPluginSdk\Entities\Address;
use GingerPluginSdk\Entities\Customer;
use GingerPluginSdk\Entities\Line;
use GingerPluginSdk\Entities\Order;
use GingerPluginSdk\Entities\PaymentMethodDetails;
use GingerPluginSdk\Entities\Transaction;
use GingerPluginSdk\Properties\Amount;
use GingerPluginSdk\Properties\Birthdate;
use GingerPluginSdk\Properties\Country;
use GingerPluginSdk\Properties\Currency;
use GingerPluginSdk\Entities\Client;
use GingerPluginSdk\Properties\EmailAddress;
use GingerPluginSdk\Properties\Locale;
use GingerPluginSdk\Properties\Percentage;
use GingerPluginSdk\Properties\RawCost;
use GingerPluginSdk\Properties\VatPercentage;
use Lib\interfaces\GingerTermsAndConditions;

class GingerOrderBuilder
{

    private $paymentMethod;
    private $cart;

    private $prestashopCustomer;
    private $prestashopAddress;
    private $prestashopCountry;

    private $locale;


    public function __construct($paymentMethod, $cart, $locale = '')
    {
        $this->paymentMethod = $paymentMethod;
        $this->cart = $cart;

        $this->prestashopCustomer = new \Customer((int)$this->cart->id_customer);
        $this->prestashopAddress = new \Address((int)$this->cart->id_address_invoice);
        $this->prestashopCountry = new \Country((int)$this->prestashopAddress->id_country);

        $this->locale = $locale;

    }

    public function getBuiltOrder(): Order
    {
        return new Order(
            currency: $this->getOrderCurrency(),
            amount: $this->getAmountInCents(),
            transactions: $this->getOrderTransactions(),
            customer: $this->getCustomerInformation(),
            orderLines: $this->getOrderLines($this->cart),
            client: $this->getClient(),
            webhook_url: $this->getWebhookURL(),
            return_url: $this->getReturnURL(),
            merchantOrderId: $this->getMerchantOrderId(),
            description: $this->getOrderDescription());
    }

    /**
     * @return Customer
     */
    public function getCustomerInformation(): Customer
    {
        return new Customer(
            additionalAddresses: new AdditionalAddresses($this->getCustomerAddress(), $this->getBillingAddress()),
            firstName: $this->getFirstName(),
            lastName: $this->getLastName(),
            emailAddress: $this->getEmailAddress(),
            gender: $this->getGender(),
            phoneNumbers: $this->getPhoneNumbers(),
            birthdate: $this->getBirthday() ? new Birthdate($this->getBirthday()) : null,
            ipAddress: $this->getIPAddress(),
            locale: $this->getLocale(),
            merchantCustomerId: $this->getMerchantCustomerId()
        );
    }


    public function getFirstName()
    {
        return $this->prestashopCustomer->firstname;
    }


    public function getLastName()
    {
        return $this->prestashopCustomer->lastname;
    }


    public function getMerchantCustomerID()
    {
        return $this->cart->id_customer;
    }


    public function getEmailAddress()
    {
        return new EmailAddress($this->prestashopCustomer->email);
    }

    public function getCountry(): Country
    {
        return new Country($this->prestashopCountry->iso_code);
    }

    public function getLocale()
    {
        return new Locale($this->locale);
    }

    public function getGender()
    {
        if ($this->prestashopCustomer->id_gender) {
            return $this->prestashopCustomer->id_gender == '1' ? 'male' : 'female';
        }
    }

    public function getBirthday()
    {
        if ($this->prestashopCustomer->birthday) {
            return $this->isBirthdateValid($this->prestashopCustomer->birthday) ? $this->prestashopCustomer->birthday : null;
        }
        return null;
    }

    public function getIPAddress()
    {
        return \Tools::getRemoteAddr();
    }

    public function getPhoneNumbers()
    {
        $phone_numbers = new PhoneNumbers();
        $this->prestashopAddress->phone ? $phone_numbers->addPhoneNumber($this->prestashopAddress->phone) : null;

        $this->prestashopAddress->phone_mobile ? $phone_numbers->addPhoneNumber($this->prestashopAddress->phone_mobile) :null;
        return $phone_numbers;
    }


    public function getPostCode()
    {
        return $this->prestashopAddress->postcode;
    }

    public function getCity()
    {
        return $this->prestashopAddress->city;
    }

    public function getFirstAddress()
    {

        return $this->prestashopAddress->address1;
    }

    public function getSecondAddress()
    {
        if ($this->prestashopAddress->address2 == ""){
            return $this->prestashopAddress->address1;
        }else{
            return $this->prestashopAddress->address2;
        }
    }

    public function getCustomerAddress()
    {
        return new Address(
            addressType: 'customer',
            postalCode: $this->getPostCode(),
            country: $this->getCountry(),
            address: $this->getFirstAddress()
        );
    }

    public function getBillingAddress()
    {
        return new Address(
            addressType: 'billing',
            postalCode: $this->getPostCode(),
            country: $this->getCountry(),
            address: $this->getSecondAddress()
        );
    }

    /**
     * @return string
     */
    public function getReturnURL($orderId = null)
    {
        $options = [
            'id_cart' => $this->cart->id,
            'id_module' => $this->paymentMethod->id
        ];

        if (isset($orderId)) $options['order_id'] = $orderId;

        return \Context::getContext()->link->getModuleLink(
            $this->paymentMethod->name, 'validation', $options
        );
    }

    /**
     * @return string
     */
    public function getOrderDescription()
    {
        return sprintf($this->paymentMethod->l('Your order at') . " %s", \Configuration::get('PS_SHOP_NAME'));
    }

    /**
     * @return Currency
     */
    public function getOrderCurrency()
    {
        $currency = new \Currency($this->cart->id_currency);
        return new Currency($currency->iso_code);
    }

    /**
     * @return string
     */
    public function getWebhookUrl()
    {
        return \_PS_BASE_URL_ . \__PS_BASE_URI__ . 'modules/xpate/ginger/webhook.php';
    }

    /**
     * @return string
     */
    public function getPluginVersion()
    {
        return $this->paymentMethod->version;
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    public function getPluginName()
    {
        return GingerPSPConfig::PLUGIN_NAME;
    }

    public function getPlatformName()
    {
        return 'PrestaShop8';
    }

    public function getPlatformVersion()
    {
        return _PS_VERSION_;
    }

    public function getMerchantOrderID()
    {
        return $this->paymentMethod->currentOrder;
    }

    public function getClient(): Client
    {
        return new Client(
            userAgent: $this->getUserAgent(),
            platformName: $this->getPlatformName(),
            platformVersion: $this->getPlatformVersion(),
            pluginName: $this->getPluginName(),
            pluginVersion: $this->getPluginVersion());
    }

    /**
     * Function returns transaction array
     * @return array
     * @throws Exception
     */
    public function getOrderTransactions(): Transactions
    {
        $args = [
            'paymentMethod' => $this->getPaymentMethod(),
            'paymentMethodDetails' => new PaymentMethodDetails(array_filter([
                'verified_terms_of_service' => ($this->paymentMethod instanceof GingerTermsAndConditions)
                    ? $this->getAfterPayToC()
                    : ''
            ]))
        ];

        if (\Configuration::get('GINGER_CREDITCARD_CAPTURE_MANUAL')) {
            $args['capture_mode'] = 'manual';
        }

        return new Transactions(
            new Transaction(...$args)
        );
    }
    public function getAfterPayToC(){
        return filter_input(INPUT_POST, GingerPSPConfig::PSP_PREFIX.'afterpay_terms_conditions');
    }

    /**
     * @return string
     */
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod->method_id;
    }

    /**
     * @return Amount
     */
    public function getAmountInCents($amount = '')
    {
        return new Amount($amount ? new RawCost($amount) : new RawCost($this->cart->getOrderTotal(true)));
    }

    /**
     * checks is brithdate format valid
     * && is not 0000-00-00
     *
     * @param string $birthdate
     * @return boolean
     */
    public function isBirthdateValid($birthdate)
    {
        if (preg_match("/([0-9]{4})-([0-9]{2})-([0-9]{2})/", $birthdate, $matches)) {
            return (bool)($matches[2] != '00' && $matches[3] != '00');
        }
        return false;
    }

    /**
     * @param $cart
     * @return OrderLines
     */
    public function getOrderLines($cart)
    {
        $orderLines = new OrderLines();
        foreach ($cart->getProducts() as $product) {
            $orderLines->addLine(
                new Line(
                    type: 'physical',
                    merchantOrderLineId: $product['unique_id'],
                    name: $product['name'],
                    quantity: (int)$product['cart_quantity'],
                    amount: $this->getAmountInCents(\Tools::ps_round($product['price_wt'])),
                    vatPercentage: new VatPercentage((int)$product['rate'] * 100),
                    currency: $this->getOrderCurrency(),
                    url: $this->paymentMethod->getProductURL($product),
                )
            );
        }

        $shippingFee = $cart->getOrderTotal(true, \Cart::ONLY_SHIPPING);

        if ($shippingFee > 0) {
            $orderLines->addLine($this->getShippingOrderLine($cart, $shippingFee));
        }
        return $orderLines;
    }

    /**
     * @param $product
     * @return string|null
     */
    public function getProductEAN($product)
    {
        return (key_exists('ean13', $product) && strlen($product['ean13']) > 0) ? $product['ean13'] : null;
    }

    /**
     * @param $cart
     * @param $shippingFee
     * @return Line
     */
    public function getShippingOrderLine($cart, $shippingFee)
    {
        return new Line(
            type: 'shipping_fee',
            merchantOrderLineId: (string)(count($cart->getProducts()) + 1),
            name: $this->paymentMethod->l("Shipping Fee"),
            quantity: 1,
            amount: $this->getAmountInCents($shippingFee),
            vatPercentage: new VatPercentage(new Percentage((float)$this->getAmountInCents($this->paymentMethod->getShippingTaxRate($cart)))),
            currency: $this->getOrderCurrency(),
        );
    }
}


