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

class AdyenPaymentResponse extends AbstractModel
{
    private static $tableName = 'adyen_payment_response';

    /**
     * @param $cartId
     * @param $resultCode
     * @param $response
     * @return bool
     */
    public function insertPaymentResponse($cartId, $resultCode, $response)
    {
        $data = array(
            'id_cart' => (int)$cartId,
            'result_code' => pSQL($resultCode),
            'response' => pSQL($this->jsonEncodeIfArray($response))
        );

        return $this->dbInstance->insert(self::$tableName, $data);
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
     * @param $resultCode
     * @param $response
     * @return bool
     */
    public function updatePaymentResponseByCartId($cartId, $resultCode, $response)
    {
        $data = array(
            'result_code' => $resultCode,
            'response' => $this->jsonEncodeIfArray($response)
        );

        return $this->dbInstance->update(self::$tableName, $data, '`id_cart` = "' . (int)$cartId . '"');
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
