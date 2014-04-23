<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category PayIntelligent
 * @package PayIntelligent_RatePAY
 * @copyright Copyright (c) 2011 PayIntelligent GmbH (http://www.payintelligent.de)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class PayIntelligent_Ratepay_Helper_Query extends Mage_Core_Helper_Abstract
{

    /**
     * product names to method names
     *
     * @var array
     */
    private $products2Methods = array("INVOICE" => "ratepay_rechnung",
                                      "INSTALLMENT" => "ratepay_rate",
                                      "ELV" => "ratepay_directdebit",
                                      "PREPAYMENT" => "ratepay_vorkasse");

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    public function isPaymentQueryActive($quote)
    {
        return Mage::getStoreConfig('payment/' . $quote->getPayment()->getMethod() . '/active', $quote->getStoreId());
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    public function validation($quote)
    {
        $helper_data = Mage::helper('ratepay/data');

        if (!$helper_data->isDobSet($quote) || $helper_data->isValidAge($quote->getCustomer()->getDob()) != "success") {
            return false;
        }

        if (!$helper_data->isPhoneSet($quote)) {
            return false;
        }

        $totalAmount = $quote->getGrandTotal();
        $minAmount = $this->_getOuterLimitMin($quote);
        $maxAmount = $this->_getOuterLimitMax($quote);

        if ($totalAmount < $minAmount || $totalAmount > $maxAmount) {
            return false;
        }

        return true;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    public function relevantOrderChanges($currentQuote, $previousQuote) {
        if ($currentQuote['basket']['amount'] > $previousQuote['basket']['amount']) {
            if ($previousQuote['Result'] == true) {
                return true;
            }
        }
        if ($currentQuote['basket']['amount'] < $previousQuote['basket']['amount']) {
            if ($previousQuote['Result'] != true) {
                return true;
            }
        }

        if ($currentQuote['customer']['firstname'] != $previousQuote['customer']['firstname']) {
            return true;
        }
        if ($currentQuote['customer']['lastname'] != $previousQuote['customer']['lastname']) {
            return true;
        }
        if ($currentQuote['customer']['dob'] != $previousQuote['customer']['dob']) {
            return true;
        }

        if ($currentQuote['customer']['billing'] != $previousQuote['customer']['billing']) {
            return true;
        }
        if ($currentQuote['customer']['shipping'] != $previousQuote['customer']['shipping']) {
            return true;
        }

        return true;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    public function getQuerySubType($quote)
    {
        $subType = false;

        $b2c = Mage::getStoreConfig('payment/' . $quote->getPayment()->getMethod() . '/b2c', $quote->getStoreId());
        $b2b = Mage::getStoreConfig('payment/' . $quote->getPayment()->getMethod() . '/b2b', $quote->getStoreId());
        $b2c_delivery = Mage::getStoreConfig('payment/' . $quote->getPayment()->getMethod() . '/delivery_address_b2c', $quote->getStoreId());
        $b2b_delivery = Mage::getStoreConfig('payment/' . $quote->getPayment()->getMethod() . '/delivery_address_b2b', $quote->getStoreId());

        if ($quote->getBillingAddress()->getCompany()) {
            if ($this->_differentAddresses($quote->getBillingAddress(), $quote->getShippingAddress())) {
                $subType = $b2b_delivery;
            } else {
                $subType = $b2b;
            }
        } else {
            if ($this->_differentAddresses($quote->getBillingAddress(), $quote->getShippingAddress())) {
                $subType = $b2c_delivery;
            } else {
                $subType = $b2c;
            }
        }

        return (empty($subType)) ? false : $subType;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array
     */
    public function getProducts(array $result_products)
    {
        if (empty($result_products) ||
            !is_array($result_products) ||
            count($result_products) == 0) {
            return false;
        }

        $products = array();
        foreach ($result_products as $element) {
            $products[] = $this->products2Methods[(string) $element->attributes()->{'method'}];
        }

        return $products;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    private function _differentAddresses($billingAddress, $shippingAddress) {
        if ($billingAddress->getFirstname() != $shippingAddress->getFirstname()) {
            return true;
        }
        if ($billingAddress->getLastname() != $shippingAddress->getLastname()) {
            return true;
        }
        if ($billingAddress->getStreetFull() != $shippingAddress->getStreetFull()) {
            return true;
        }
        if ($billingAddress->getPostcode() != $shippingAddress->getPostcode()) {
            return true;
        }
        if ($billingAddress->getCity() != $shippingAddress->getCity()) {
            return true;
        }

        return false;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    private function _getOuterLimitMin($quote) {
        foreach ($this->products2Methods AS $product => $method) {
            if (Mage::getStoreConfig('payment/' . $method . '/active', $quote->getStoreId()) == "1") {
                if (!isset($outerLimitMin) || $outerLimitMin > Mage::getStoreConfig('payment/' . $method . '/min_order_total', $quote->getStoreId())) {
                    $outerLimitMin = Mage::getStoreConfig('payment/' . $method . '/min_order_total', $quote->getStoreId());
                }
            }
        }

        return $outerLimitMin;
    }

    /**
     * Extracts allowed products from the response xml (associative) and returns an simple array
     *
     * @param array $result_products
     * @return array/boolean
     */
    private function _getOuterLimitMax($quote) {
        foreach ($this->products2Methods AS $product => $method) {
            if (Mage::getStoreConfig('payment/' . $method . '/active', $quote->getStoreId()) == "1") {
                if (!isset($outerLimitMax) || $outerLimitMax < Mage::getStoreConfig('payment/' . $method . '/max_order_total', $quote->getStoreId())) {
                    $outerLimitMax = Mage::getStoreConfig('payment/' . $method . '/max_order_total', $quote->getStoreId());
                }
            }
        }

        return $outerLimitMax;
    }

}