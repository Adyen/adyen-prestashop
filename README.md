# Adyen Payment plugin for Prestashop
Use Adyen's plugin for Prestashop to offer frictionless payments online, in-app, and in-store.

## Integration
The plugin integrates card component(Secured Fields) using Adyen Checkout for all card payments.

## Requirements
This plugin supports Prestashop version 1.6 or 1.7.

## Collaboration
We commit all our new features directly into our GitHub repository.
But you can also request or suggest new features or code changes yourself!

## Installation
Copy the folders into the **/modules/adyen** folder of your main Prestashop environment.

Search for Adyen in the **Modules and Services**(1.6) / **Module Manager**(1.7) menu in the Prestashop admin panel and select Enable

## Set up Adyen Customer Area

For authenticating API requests from your Prestashop platform, you need to provide an API key. 

To generate an API Key:

1.  Log in to your [Adyen Customer Area](https://ca-test.adyen.com).
2.  Go to **Account** > **Users**, and click the user **ws@Company.[YourCompanyAccount]**.
3.  Under **Authentication**, click **Generate New API Key**. 

    (\*) Don't forget to copy and securely store the API Key in your system – you will need it later when [Configuring the plugin in Prestashop]().

4.  Click **Save** at the bottom of the page.

## Configuring the plugin in Prestashop
The configuration window is the same for both Prestashop 1.6 and 1.7

Before you begin, make sure that you have set up your Adyen Customer Area.

Configure the Adyen plugin in your Prestashop admin panel: 

1.  Log in to your Prestashop admin panel. 
2.  In the left navigation bar, go to **Modules and Services**(1.6) / **Modules** > **Module Manager**(1.7). 
3.  Search for Adyen in the search bar
4.  Click on **Install** / **Enable Module**
5.  Click on **Configure** and enter the following values:

<table>
   <colgroup>
      <col style="width: 21%" />
      <col style="width: 78%" />
   </colgroup>
   <thead>
      <tr class="header">
         <th>Field</th>
         <th>Description</th>
      </tr>
   </thead>
   <tbody>
      <tr>
         <td><strong>Merchant Account</strong></td>
         <td>
            <p>Name of your Adyen merchant account for which the payments will be processed.</p>
         </td>
      </tr>
      <tr>
         <td><strong>Test/Production Mode</strong></td>
         <td>
            <p>Select whether you want to use test or production mode.</p>
         </td>
      </tr>
      <tr>
         <td><strong>Notification User Name(not used at the moment)</strong></td>
         <td>This can be any username, as long as it matches the username for basic authentication that you <a href="https://ca-test.adyen.com">entered in your Adyen Customer Area</a>.</td>
      </tr>
      <tr>
         <td><strong>Notification Password(not used at the moment)</strong></td>
         <td>
            <p>This can be any password, as long as it matches the password for basic authentication that you <a href="https://ca-test.adyen.com">entered in your Adyen Customer Area</a>.<br/></p>
         </td>
      </tr>
      <tr>
         <td><strong>API key for Test</strong></td>
         <td>Your API key from Adyen test Customer Area (*) .</td>
      </tr>
      <tr>
         <td><strong>API key for Live</strong></td>
         <td>Your API key from Adyen live Customer Area (*) .</td>
      </tr>
      <tr>
         <td><strong>Live endpoint prefix</strong></td>
         <td>
            <p>The URL prefix <strong>[random]-[company name]</strong> from your Adyen live > <strong>Account</strong> > <strong>API URLs</strong>. For more information, refer to <a href="https://docs.adyen.com/development-resources/live-endpoints#description">Checkout endpoints</a>.</p>
         </td>
      </tr>
   </tbody>
</table>

(\*) You noted this down when you Set up Adyen Customer Area.

## Documentation(will be there....)
[Prestashop documentation](https://docs.adyen.com/developers/plugins/prestashop)

## Support
You can create issues on our Prestashop Repository. In case of specific problems with your account, please contact  <a href="mailto:support@adyen.com">support@adyen.com</a>.

## API Library
This module is using the Adyen APIs Library for PHP for all (API) connections to Adyen.
<a href="https://github.com/Adyen/adyen-php-api-library" target="_blank">This library can be found here</a>

## License
MIT license. For more information, see the LICENSE file.
