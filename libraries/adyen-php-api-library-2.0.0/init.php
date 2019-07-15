<?php

// order does matter!
// Adyen singleton
require(dirname(__FILE__) . '/src/Adyen/AdyenException.php');
require(dirname(__FILE__) . '/src/Adyen/Service.php');
require(dirname(__FILE__) . '/src/Adyen/ConfigInterface.php');
require(dirname(__FILE__) . '/src/Adyen/Config.php');
require(dirname(__FILE__) . '/src/Adyen/ApiKeyAuthenticatedService.php');
require(dirname(__FILE__) . '/src/Adyen/Client.php');
require(dirname(__FILE__) . '/src/Adyen/ConnectionException.php');
require(dirname(__FILE__) . '/src/Adyen/Contract.php');
require(dirname(__FILE__) . '/src/Adyen/Environment.php');
require(dirname(__FILE__) . '/src/Adyen/TransactionType.php');


// HttpClient
require(dirname(__FILE__) . '/src/Adyen/HttpClient/ClientInterface.php');
require(dirname(__FILE__) . '/src/Adyen/HttpClient/CurlClient.php');

// Service
require(dirname(__FILE__) . '/src/Adyen/Service/AbstractResource.php');
require(dirname(__FILE__) . '/src/Adyen/Service/AbstractCheckoutResource.php');

require(dirname(__FILE__) . '/src/Adyen/Service/Account.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Checkout.php');
require(dirname(__FILE__) . '/src/Adyen/Service/CheckoutUtility.php');
require(dirname(__FILE__) . '/src/Adyen/Service/DirectoryLookup.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Fund.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Modification.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Notification.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Payment.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Payout.php');
require(dirname(__FILE__) . '/src/Adyen/Service/Recurring.php');

// Service ResourceModel
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/CloseAccount.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/CloseAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/CreateAccount.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/CreateAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/DeleteBankAccounts.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/DeleteShareholders.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/GetAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/GetUploadedDocuments.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/SuspendAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/UnSuspendAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/UpdateAccount.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/UpdateAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/UpdateAccountHolderState.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Account/UploadDocument.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Checkout/PaymentMethods.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Checkout/Payments.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Checkout/PaymentsDetails.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Checkout/PaymentSession.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Checkout/PaymentsResult.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/CheckoutUtility/OriginKeys.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/DirectoryLookup/Directory.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/AccountHolderBalance.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/AccountHolderTransactionList.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/PayoutAccountHolder.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/RefundNotPaidOutTransfers.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/SetupBeneficiary.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Fund/TransferFunds.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Modification/AdjustAuthorisation.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Modification/Cancel.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Modification/CancelOrRefund.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Modification/Capture.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Modification/Refund.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/CreateNotificationConfiguration.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/DeleteNotificationConfigurations.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/GetNotificationConfiguration.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/GetNotificationConfigurationList.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/TestNotificationConfiguration.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Notification/UpdateNotificationConfiguration.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payment/Authorise.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payment/Authorise3D.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payment/Authorise3DS2.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payment/TerminalCloudAPI.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/ThirdParty/ConfirmThirdParty.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/ThirdParty/DeclineThirdParty.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/ThirdParty/StoreDetail.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/ThirdParty/StoreDetailsAndSubmitThirdParty.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/ThirdParty/SubmitThirdParty.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/Confirm.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/Decline.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/StoreDetailsAndSubmit.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Payout/Submit.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Recurring/Disable.php');
require(dirname(__FILE__) . '/src/Adyen/Service/ResourceModel/Recurring/ListRecurringDetails.php');

// Util
require(dirname(__FILE__) . '/src/Adyen/Util/Util.php');