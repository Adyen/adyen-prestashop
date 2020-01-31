# Adyen Payment plugin for PrestaShop
Use Adyen's plugin for PrestaShop to offer frictionless payments online, in-app, and in-store.

## Integration
The plugin integrates card component(Secured Fields) using Adyen Checkout for all card payments. Local payment methods are integrated with Checkout Payment Methods.

## Requirements
This plugin supports PrestaShop version 1.6 or 1.7.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.

## Installation
Copy the folders into the **/modules/adyen** folder of your main PrestaShop environment.

Search for Adyen in the **Modules and Services**(1.6) / **Module Catalog**(1.7) menu in the PrestaShop admin panel and select Enable

## Set up Adyen Customer Area

For authenticating API requests from your PrestaShop platform, you need to provide an API key. 

To generate an API Key:

1.  Log in to your [Adyen Customer Area](https://ca-test.adyen.com).
2.  Go to **Account** > **Users**, and click the user **ws@Company.[YourCompanyAccount]**.
3.  Under **Authentication**, click **Generate New API Key**. 

    (\*) Don't forget to copy and securely store the API Key in your system – you will need it later when [Configuring the plugin in PrestaShop]().

4.  Click **Save** at the bottom of the page.

## Configuring the plugin in PrestaShop
The configuration window is the same for both PrestaShop 1.6 and 1.7

Before you begin, make sure that you have set up your Adyen Customer Area.

Configure the Adyen plugin in your PrestaShop admin panel: 

1.  Log in to your PrestaShop admin panel. 
2.  In the left navigation bar, go to **Modules and Services**(1.6) / **Modules** > **Module Manager**(1.7). 
3.  Search for Adyen in the search bar
4.  Click on **Install** / **Enable Module**
5.  Click on **Configure** and enter the following values:

|Field|Description|
|--- |--- |
|**Merchant Account**|Name of your Adyen merchant account for which the payments will be processed.|
|**Test/Production Mode**|Select whether you want to use test or production mode.|
|**Notification User Name**|This can be any username, as long as it matches the username for basic authentication that you entered in your Adyen Customer Area.|
|**Notification Password**|This can be any password, as long as it matches the password for basic authentication that you entered in your Adyen Customer Area.|
|**API key for Test**|Your API key from Adyen test Customer Area (*) .|
|**API key for Live**|Your API key from Adyen live Customer Area (*) .|
|**Live endpoint prefix**|The URL prefix [random]-[company name] from your Adyen live > Account > API URLs. For more information, refer to Checkout endpoints.|

(\*) You noted this down when you Set up Adyen Customer Area.

## Deprecation strategy
Whenever a not private function or property is tagged deprecated, please be aware that in the next major release it will be permanently removed.

## Support
You can create issues on our PrestaShop Repository. In case of specific problems with your account, please contact  <a href="mailto:support@adyen.com">support@adyen.com</a>.

## API Library
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## License
MIT license. For more information, see the LICENSE file.
