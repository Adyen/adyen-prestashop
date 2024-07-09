# Adyen Payment plugin for PrestaShop
Use Adyen's plugin for PrestaShop to offer frictionless payments online, in-app, and in-store.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.

## Requirements
This plugin supports PrestaShop versions 1.7.5.0 to 8.1.6. 

## Documentation
Please find the relevant documentation for
- [How to start with Adyen](https://www.adyen.com/get-started)
- [Plugin documentation](https://github.com/Adyen/adyen-prestashop/wiki) for installation and configuration guidance.

## Support
If you have a feature request, or spotted a bug or a technical problem, create a GitHub issue. For other questions, contact our [support team](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

Note: if you are still using an **older version** of the Adyen Prestashop integration (**below v5.0**) please refer to [this documentation](https://github.com/Adyen/adyen-prestashop/wiki/Home/ab7b1ee3c889c2b1fc3395cf21f55fcfcdfac1b2).

#### Important information ####
Support deprecation plan for old plugins Prestashop (below major release v5.0):
1. Only critical functionality and security updates until June 2024.
2. Only critical security updates from June 2024 until June 2025.
3. Support will be fully suspended for old Prestashop plugins from June 2025 onwards.

### Disclaimer
We only support the plugin with no customizations. 
Please make sure before you raise an issue that you revisit it on a newly installed "vanilla" PrestaShop environment. With this practise you can make sure that the issue is not created by a customization or a third party plugin.

# For developers

## Integration
The plugin integrates card component (Secured Fields) using Adyen Checkout for all card payments. Currently, the following versions of Web components and Checkout API are utilized in the code:
* **Checkout API version:** v69
* **Checkout Web Component version:** 5.31.1

## License
MIT license. For more information, see the LICENSE file.
