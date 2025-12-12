$(document).ready(function () {
    let submitButtonReplacingComponents = ['applepay', 'amazonpay', 'paywithgoogle', 'googlepay', 'paypal'];
    let divPlaceOrder = document.getElementById('payment-confirmation');
    let placeOrder = $(divPlaceOrder).find('[type=submit]');
    let formConditions = document.getElementById('conditions-to-approve');
    let checkBox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]');
    let checkoutController = null;
    let paymentId = 0;
    let paymentOptions = $('input[name="payment-option"]');
    let adyenPaymentMethods = document.getElementsByClassName('adyen-payment-method');
    let paymentUrl = document.getElementsByClassName('adyen-action-url')[0];
    let checkoutUrl = document.getElementsByClassName('adyen-checkout-url')[0];
    let token = document.getElementsByClassName('adyen-token')[0].value;
    let saveStateDataUrl = document.getElementsByClassName('adyen-state-data-url')[0].value;
    let getStateDataUrl = document.getElementsByClassName('adyen-get-state-data-url')[0].value;
    let totalDiscount = 0;
    let minorTotalDiscount = 0;
    let remainingAmount = -1;
    let orderTotalAmount = document.getElementsByClassName('adyen-order-total-amount')[0];

    checkoutController = getCheckoutController(document.getElementsByClassName('adyen-config-url')[0].value);

    $('.payment-option').filter(function () {
        return $(this).find('input[data-module-name="adyenofficial"]').length > 0;
    }).addClass('adyen-image')

    for (let adyenPaymentMethod of adyenPaymentMethods) {
        adyenPaymentMethod.addEventListener('click', (event) => {
            event.stopPropagation();
        })
    }

    checkBox.change(function (event) {
        if (checkBox.is(":checked") && checkoutController) {
            let paymentForm = $("#pay-with-" + paymentId + "-form");
            let type = paymentForm.find('[name=adyen-type]').val();
            if (paymentForm.children(".adyen-payment-method").length > 0
                && !submitButtonReplacingComponents.includes(type)) {
                handleStateChange();
                event.stopPropagation();
            }
        }
    })

    if (paymentOptions.length === 1) {
        let paymentForm = $("#pay-with-payment-option-1-form");
        if (paymentForm.children(".adyen-payment-method").length > 0) {
            paymentId = "payment-option-1";
            mountComponent(paymentForm);
        }
    }

    $(document).on("change", "input[name='payment-option']", function () {
        paymentId = this.id;
        let paymentForm = $("#pay-with-" + paymentId + "-form");
        let type = paymentForm.find('[name=adyen-type]').val();
        if (paymentForm.children(".adyen-payment-method").length > 0
            && !submitButtonReplacingComponents.includes(type)) {
            mountComponent(paymentForm);
        }
    });

    remainingAmount = sessionStorage.getItem('remainingAmount') ? parseFloat(sessionStorage.getItem('remainingAmount')) : -1;
    totalDiscount = sessionStorage.getItem('totalDiscount') ? parseFloat(sessionStorage.getItem('totalDiscount')) : 0;
    minorTotalDiscount = sessionStorage.getItem('minorTotalDiscount') ? parseInt(sessionStorage.getItem('minorTotalDiscount')) : 0;
    let orderTotalAmountValue = orderTotalAmount ? parseFloat(orderTotalAmount.value) : -1;
    if (orderTotalAmountValue !== -1 && remainingAmount !== -1 && orderTotalAmountValue !== remainingAmount + totalDiscount) {
        remainingAmount = orderTotalAmountValue - totalDiscount;
        sessionStorage.setItem('remainingAmount', remainingAmount);
    }

    checkoutController.getGiftCardStateData().then(responses => {
        let originalPaymentId = paymentId;
        if (responses.giftCardsData !== undefined) {
            for (let response of responses.giftCardsData) {
                paymentId = response.paymentId;
                renderGiftCardDetails(response, response.cardValue, response);
            }
        }

        paymentId = originalPaymentId;
    });

    function mountComponent(paymentForm) {
        let type = paymentForm.find('[name=adyen-type]').val();
        let configUrl = paymentForm.find(".adyen-config-url").val();
        let form = paymentForm.find(".adyen-form-" + type)[0];
        let stored = paymentForm.find('[name=adyen-stored-value]').val();
        let paymentMethodId = paymentForm.find('[name=adyen-payment-method-id]').val();

        checkoutController = getCheckoutController(configUrl);

        if (stored) {
            checkoutController.mount(type, form, paymentMethodId);

            return;
        }

        checkoutController.mount(type, form);
    }

    function handleStateChange() {
        let paymentForm = $("#pay-with-" + paymentId + "-form");
        let prestaVersion = paymentForm.find('[name=adyen-presta-version]').val();
        let checkbox = $(formConditions).find('[name="conditions_to_approve[terms-and-conditions]"]');

        if (checkoutController.isPaymentMethodStateValid() && ((checkbox.length && checkbox.is(":checked")) || !checkbox.length)) {
            let addData = paymentForm.find('[name=adyen-additional-data]');
            let giftcardData = paymentForm.find('[name=adyen-giftcards-data]');
            let cardsData = document.getElementsByClassName('adyen-giftcard-state-data');
            let stateData = '';

            for (let element of cardsData) {
                if (element.value !== '') {
                    stateData += element.value + ',';
                }
            }

            giftcardData.val('[' + stateData.slice(0, -1) + ']');
            addData.val(checkoutController.getPaymentMethodStateData());
            enablePlaceOrderButton(prestaVersion);

            return
        }

        disablePlaceOrderButton(prestaVersion);
    }

    function disablePlaceOrderButton(prestaVersion) {
        placeOrder.attr('disabled', 'disabled');
        if (prestaVersion >= '1.7.7.2') {
            placeOrder.addClass('disabled');
        }
    }

    function enablePlaceOrderButton(prestaVersion) {
        placeOrder.removeAttr('disabled')
        if (prestaVersion >= '1.7.7.2') {
            placeOrder.removeClass('disabled')
        }
    }

    function getCheckoutController(checkoutConfigUrl) {
        if (checkoutController && totalDiscount === 0) {
            return checkoutController;
        }

        return new AdyenComponents.CheckoutController({
            "checkoutConfigUrl": checkoutConfigUrl + '?discountAmount=' + totalDiscount,
            "onStateChange": handleStateChange,
            "onClickToPay": handleClickOnPay,
            "balanceCheck": checkBalance,
            "saveStateDataUrl": saveStateDataUrl + '?token=' + token,
            "getStateDataUrl": getStateDataUrl + '?token=' + token
        });
    }

    function handleClickOnPay() {
        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: paymentUrl.value + '?isXHR=1',
            data: {},
            success: function (response) {
                sessionStorage.removeItem('remainingAmount');
                sessionStorage.removeItem('totalDiscount');
                sessionStorage.removeItem('minorTotalDiscount');

                if (response.nextStepUrl) {
                    window.location.href = response.nextStepUrl;
                    return;
                }

                try {
                    const checkoutUrlObject = new URL(checkoutUrl.value);
                    window.location.href = checkoutUrlObject.href;
                } catch (err) {
                    console.error('Invalid URL, redirection aborted.', err);
                }
            },
            error: function () {
                try {
                    const checkoutUrlObject = new URL(checkoutUrl.value);
                    window.location.href = checkoutUrlObject.href;
                } catch (err) {
                    console.error('Invalid URL, redirection aborted.', err);
                }
            }
        });
    }

    function checkBalance(resolve, reject, data) {
        let checkBalanceUrl = document.getElementsByClassName('adyen-balance-check-url')[0].value;

        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: checkBalanceUrl + '?remainingAmount=' + remainingAmount,
            data: data,
            success: function (response) {
                if (!response.success) {
                    reject(response);

                    return;
                }

                resolve(response.response);
                delete response.response;
                checkoutController.getGiftCardStateData().then(savedData => {
                    checkoutController.getPaymentMethodStateData().then(stateData => {
                        let cardValue = parseFloat(response.majorValue);

                        if (remainingAmount === -1) {
                            remainingAmount = response.orderTotal;
                        }

                        if (response.resultCode === 'Success') {
                            cardValue = parseFloat(remainingAmount).toFixed(2);
                            remainingAmount = 0;
                            totalDiscount = response.orderTotal;
                        } else {
                            remainingAmount -= response.majorValue;
                            totalDiscount = parseFloat(totalDiscount) + parseFloat(response.majorValue);
                        }

                        minorTotalDiscount = parseInt(minorTotalDiscount) + parseInt(response.minorValue);

                        let data = [];
                        data['cardAmount'] = remainingAmount === 0 ? response.minorOrderTotal - minorTotalDiscount : parseInt(response.minorValue);

                        sessionStorage.setItem('remainingAmount', remainingAmount);
                        sessionStorage.setItem('totalDiscount', totalDiscount);
                        sessionStorage.setItem('minorTotalDiscount', minorTotalDiscount);
                        response['cardValue'] = cardValue;
                        response['paymentId'] = paymentId;
                        response['stateData'] = JSON.stringify(data);
                        stateData.stateData.majorValue = response.majorValue;
                        stateData.stateData.orderTotal = response.orderTotal;
                        stateData.stateData.minorValue = response.minorValue;
                        stateData.stateData.responseCurrency = response.responseCurrency;
                        stateData.stateData.currency = response.currency;
                        stateData.stateData.minorOrderTotal = response.minorOrderTotal;
                        stateData.stateData.paymentId = paymentId;
                        stateData.stateData.cardAmount = parseInt(response.minorValue);
                        stateData.stateData.cardValue = cardValue;

                        if (savedData.giftCardsData === undefined) {
                            savedData.giftCardsData = [stateData.stateData];
                        } else {
                            savedData.giftCardsData.push(stateData.stateData);
                        }

                        checkoutController.saveGiftCardStateData(savedData.giftCardsData);

                        renderGiftCardDetails(response, cardValue, stateData.stateData);
                    });
                })
            },
            error: function (response) {
                reject(response)
            }
        });
    }

    function removeCard(event) {
        event.preventDefault();
        let parentEl = event.target.parentElement;
        let cardValue = parentEl.getAttribute('adyen-card-value');
        let minorValue = parentEl.getAttribute('adyen-card-value-minor');
        let currency = parentEl.getAttribute('adyen-card-currency');
        let adyenMessage = document.getElementsByClassName('adyen-message')[0];
        let stateData = parentEl.getElementsByClassName('adyen-giftcard-state-data')[0];

        if (typeof adyenMessage !== 'undefined') {
            adyenMessage.remove();
        }

        totalDiscount = parseFloat(totalDiscount) - parseFloat(cardValue);
        remainingAmount = parseFloat(remainingAmount) + parseFloat(cardValue);
        minorTotalDiscount = parseInt(minorTotalDiscount) - parseInt(minorValue);

        sessionStorage.setItem('remainingAmount', remainingAmount);
        sessionStorage.setItem('totalDiscount', totalDiscount);
        sessionStorage.setItem('minorTotalDiscount', minorTotalDiscount);

        checkoutController.getGiftCardStateData().then(savedData => {
            if (savedData.giftCardsData !== undefined) {
                for (let data of savedData.giftCardsData) {
                    if (JSON.stringify(data) === stateData.value) {
                        let index = savedData.giftCardsData.indexOf(data);

                        if (paymentId === data.paymentId) {
                            let paymentForm = $("#pay-with-" + paymentId + "-form");
                            mountComponent(paymentForm);
                        }

                        if (index !== -1) {
                            savedData.giftCardsData.splice(index, 1);
                        }
                    }
                }
            }

            checkoutController.saveGiftCardStateData(savedData.giftCardsData).then(savedData => {
                renderCartSummary(currency);

                stateData.value = '';
                parentEl.hidden = true;

                let paymentOptions = $('input[name="payment-option"]');

                for (let paymentOption of paymentOptions) {
                    paymentOption.disabled = false;
                }
            })
        });
    }

    function renderCartSummary(currency) {
        let totalDiv = $(".cart-summary-totals")[0];
        let cartSummary = totalDiv.parentNode;
        let adyenSummary = $('.adyen-cart-summary')[0];

        if (typeof adyenSummary !== 'undefined') {
            adyenSummary.remove();
        }

        adyenSummary = document.createElement('div');
        adyenSummary.classList.add('card-block', 'adyen-cart-summary');

        let adyenInnerBlock = document.createElement('div');
        adyenInnerBlock.classList.add('card-block', 'cart-summary-subtotals-container');
        adyenInnerBlock.style.borderTop = "none";
        adyenInnerBlock.style.paddingTop = "0";
        let redeemed = document.createElement('div');
        redeemed.classList.add('cart-summary-line', 'cart-summary-subtotals')
        let redeemedTitle = document.createElement('span');
        let redeemedTitleValue = $('[name="adyen-giftcard-discount"]')[0];
        redeemedTitle.classList.add('label');
        redeemedTitle.innerText = redeemedTitleValue.value;
        let redeemedValue = document.createElement('span');
        redeemedValue.classList.add('value');
        redeemedValue.innerText = currency + totalDiscount.toFixed(2);
        redeemed.appendChild(redeemedTitle);
        redeemed.appendChild(redeemedValue);

        let remaining = document.createElement('div');
        remaining.classList.add('cart-summary-line', 'cart-summary-subtotals');
        let remainingTitle = document.createElement('span');
        let remainingTitleValue = $('[name="adyen-giftcard-remaining-amount"]')[0];
        remainingTitle.classList.add('label');
        remainingTitle.innerText = remainingTitleValue.value;
        let remainingValue = document.createElement('span');
        remainingValue.classList.add('value');
        remainingValue.innerText = currency + remainingAmount.toFixed(2);
        remaining.appendChild(remainingTitle);
        remaining.appendChild(remainingValue);

        adyenInnerBlock.appendChild(redeemed);
        adyenInnerBlock.appendChild(remaining);

        adyenSummary.appendChild(adyenInnerBlock);
        cartSummary.insertBefore(adyenSummary, totalDiv);
    }

    function renderGiftCardDetails(response, cardValue, savedData) {
        let paymentForm = $("#pay-with-" + paymentId + "-form");
        let paymentMethod = $("#" + paymentId + "-container")[0];
        let configUrl = paymentForm.find(".adyen-config-url").val();

        if (!checkoutController) {
            checkoutController = getCheckoutController(configUrl);
        }

        let cardInfo = document.createElement("div");
        cardInfo.style.display = 'flex';
        let cardLabel = document.createElement("p");
        cardLabel.style.flexGrow = 1;
        let removeLink = document.createElement("a");
        let removeLabel = $('[name="adyen-giftcard-remove"]')[0];
        removeLink.innerText = removeLabel.value;
        removeLink.href = "#";
        removeLink.addEventListener('click', removeCard);
        let stateData = document.createElement('input');
        stateData.type = 'hidden';
        stateData.value = JSON.stringify(savedData);
        stateData.classList.add('adyen-giftcard-state-data');
        cardInfo.appendChild(stateData);

        let deductedAmount = $('[name="adyen-giftcard-deducted-amount"]')[0];
        cardLabel.innerText = deductedAmount.value.replace("{currencySymbol}", response.currency)
            .replace("{cardValue}", cardValue).replace("{currencyIso}", response.responseCurrency);
        cardInfo.appendChild(cardLabel);
        cardInfo.appendChild(removeLink);
        cardInfo.setAttribute('adyen-card-value-minor', parseInt(response.minorValue));
        cardInfo.setAttribute('adyen-card-value', parseFloat(cardValue));
        cardInfo.setAttribute('adyen-card-currency', response.currency);

        paymentMethod.append(cardInfo);

        renderCartSummary(response.currency);
        checkoutController.unmount();

        if (remainingAmount === 0) {
            let completelyElements = document.getElementsByClassName('adyen-message');

            if (typeof completelyElements !== 'undefined') {
                for (let element of completelyElements) {
                    element.remove();
                }
            }

            let completelyPaid = document.createElement('article');
            completelyPaid.classList.add('alert', 'alert-success', 'adyen-message');
            let messageBox = document.createElement('ul');
            let message = document.createElement('li');
            let messageValue = $('[name="adyen-giftcard-complete-order"]')[0];
            message.innerText = messageValue.value;
            messageBox.appendChild(message);
            completelyPaid.appendChild(messageBox);
            paymentMethod.appendChild(completelyPaid);

            let paymentOptions = $('input[name="payment-option"]');

            for (let paymentOption of paymentOptions) {
                paymentOption.disabled = true;
            }

            return;
        }

        mountComponent(paymentForm);
    }
})
