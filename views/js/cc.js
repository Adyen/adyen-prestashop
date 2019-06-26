// todo remove
//
// $(document).ready(function() {
//     renderSecureFields();
// });
//
//
// function renderSecureFields() {
//     var locale = "en_US";
//     // var cardGroupTypes = alt_types.slice();
//     var self = this;
//     var cardNode = document.getElementById('cardContainer');
//
//     // we can now rely on $ within the safety of our "bodyguard" function
//     var checkout = new AdyenCheckout({
//         locale: locale,
//         originKey: origin_key,
//         environment: "test",
//         risk: {
//             enabled: false
//         }
//     });
//     var card = checkout.create('card', {
//         type: 'card',
//         hasHolderName: true,
//         holderNameRequired: true,
//         // groupTypes: cardGroupTypes,
//
//         onChange: function (state, component) {
//             if (state.isValid && !component.state.errors.encryptedSecurityCode) {
//                 document.getElementById('holderName').value = state.data.paymentMethod.holderName;
//                 document.getElementById('encryptedCardNumber').value = state.data.paymentMethod.encryptedCardNumber;
//                 document.getElementById('encryptedExpiryMonth').value = state.data.paymentMethod.encryptedExpiryMonth;
//                 document.getElementById('encryptedExpiryYear').value = state.data.paymentMethod.encryptedExpiryYear;
//                 if (state.data.paymentMethod.encryptedSecurityCode) {
//                     document.getElementById('encryptedSecurityCode').value = state.data.paymentMethod.encryptedSecurityCode;
//                 }
//                 document.getElementById("allValidcard").value = true;
//             } else {
//                 resetFields();
//             }
//         }
//     });
//     card.mount(cardNode);
// }
//
// function resetFields(){
//     document.getElementById('holderName').value = "";
//     document.getElementById('encryptedCardNumber').value = "";
//     document.getElementById('encryptedExpiryMonth').value = "";
//     document.getElementById('encryptedExpiryYear').value = "";
//     document.getElementById('encryptedSecurityCode').value = "";
//     document.getElementById("allValidcard").value = "";
// }