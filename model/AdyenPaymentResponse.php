<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen PrestaShop plugin
 *
 * @author Adyen BV <support@adyen.com>
 * @copyright (c) 2020 Adyen B.V.
 * @license https://opensource.org/licenses/MIT MIT license
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 */

namespace Adyen\PrestaShop\model;

// TODO Rename database table to AdyenPayment
class AdyenPaymentResponse extends AbstractModel
{
    private static $tableName = 'adyen_payment_response';

    /**
     * @param $cartId
     * @param $resultCode
     * @param $response
     * @param null $requestAmount
     * @param null $requestCurrency
     * @return bool
     */
    public function insertOrUpdatePaymentResponse(
        $cartId,
        $resultCode,
        $response,
        $requestAmount = null,
        $requestCurrency = null
    ) {
        $data = array(
            'result_code' => pSQL($resultCode),
            'response' => pSQL($this->jsonEncodeIfArray($response))
        );

        if (null !== $requestAmount) {
            $data['request_amount'] = pSQL((int)$requestAmount);
        }

        if (null !== $requestCurrency) {
            $data['request_currency'] = pSQL($requestCurrency);
        }

        if ($this->getPaymentResponseByCartId($cartId)) {
            return $this->updatePaymentResponseByCartId($cartId, $data);
        }

        $data['id_cart'] = (int)$cartId;

        return $this->insertPaymentResponse($data);
    }

    /**
     * @param $cartId
     * @return array|bool|object|null
     */
    public function getPaymentResponseByCartId($cartId)
    {
        $sql = 'SELECT `response` FROM ' . _DB_PREFIX_ . self::$tableName
            . ' WHERE `id_cart` = "' . (int)$cartId . '"';

        return $this->jsonDecodeIfJson(
            $this->dbInstance->getValue($sql)
        );
    }

    /**
     * @param $cartId
     * @return array|bool|object|null
     */
    public function getPaymentByCartId($cartId)
    {
        $sql = 'SELECT `request_amount`, `request_currency`, `result_code`, `response` FROM ' .
            _DB_PREFIX_ . self::$tableName . ' WHERE `id_cart` = "' . (int)$cartId . '"';

        $row = $this->dbInstance->getRow($sql, false);

        if (!empty($row)) {
            $row['response'] = $this->jsonDecodeIfJson($row['response']);
        }

        return $row;
    }

    /**
     * @param $cartId
     * @param $data
     * @return bool
     */
    public function updatePaymentResponseByCartId($cartId, $data)
    {
        return $this->dbInstance->update(self::$tableName, $data, '`id_cart` = "' . (int)$cartId . '"');
    }

    /**
     * @param $data
     * @return bool
     *
     */
    public function insertPaymentResponse($data)
    {
        return $this->dbInstance->insert(self::$tableName, $data);
    }

    /**
     * @param $cartId
     * @return bool
     */
    public function deletePaymentResponseByCartId($cartId)
    {
        return $this->dbInstance->delete(self::$tableName, '`id_cart` = "' . (int)$cartId . '"');
    }

    /**
     * @param $param
     * @return mixed
     */
    private function jsonEncodeIfArray($param)
    {
        if (is_array($param)) {
            return json_encode($param);
        }

        return $param;
    }

    /**
     * @param $param
     * @return mixed
     */
    private function jsonDecodeIfJson($param)
    {
        $jsonDecoded = json_decode($param, true);

        if (json_last_error() == JSON_ERROR_NONE) {
            return $jsonDecoded;
        }

        return $param;
    }
}
