/*
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

jQuery(function ($) {
  if (!window.adyenCheckout) {
    return;
  }

  var paymentActionContainer = $('[data-adyen-payment-action-container]');
  var paymentAdditionalDataContainer = $('[data-adyen-payment-additional-data-container]');

  // container doesn't exist, something went wrong on the template side
  if (paymentActionContainer.length) {
    var paymentAction = paymentActionContainer.data().adyenPaymentAction;

    // If payment action is false, don't try to render the component
    if (paymentAction) {
      handlePaymentAction(paymentAction);
    }
  }

  // container doesn't exist, something went wrong on the template side
  if (paymentAdditionalDataContainer.length) {
    var paymentAdditionalData = paymentAdditionalDataContainer.data() .adyenPaymentAdditionalData;

    // If payment additional data is false, don't try to render the fields
    if (paymentAdditionalData) {
      handlePaymentAdditionalData(paymentAdditionalDataContainer, paymentAdditionalData);
    }
  }
});

function handlePaymentAction(paymentAction) {
  window.adyenCheckout
    .createFromAction(paymentAction)
    .mount('[data-adyen-payment-action-container]');
}

function handlePaymentAdditionalData(paymentAdditionalDataContainer, paymentAdditionalData) {
  var element = null;

  if (paymentAdditionalData['bankTransfer.owner']) {
    element = createAdyenAdditionalDataLineElement();
    element.append(document.createTextNode('Bank transfer owner: ' + paymentAdditionalData['bankTransfer.owner']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.shopperStatement']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Shopper statement: ' + paymentAdditionalData['bankTransfer.shopperStatement']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.countryCode']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Bank transfer country code: ' + paymentAdditionalData['bankTransfer.countryCode']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.type']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Bank transfer type: ' + paymentAdditionalData['bankTransfer.type']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.iban']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Bank transfer IBAN: ' + paymentAdditionalData['bankTransfer.iban']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.swift']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Bank transfer SWIFT: ' + paymentAdditionalData['bankTransfer.swift']));
    paymentAdditionalDataContainer.append(element);
  }

  if (paymentAdditionalData['bankTransfer.reference']) {
    element = createAdyenAdditionalDataLineElement();
    element.appendChild(document.createTextNode('Bank transfer reference: ' + paymentAdditionalData['bankTransfer.reference']));
    paymentAdditionalDataContainer.append(element);
  }
}

function createAdyenAdditionalDataLineElement() {
  var element = document.createElement("div");
  element.classList.add("adyen-additional-data-line");
  return element;
}
