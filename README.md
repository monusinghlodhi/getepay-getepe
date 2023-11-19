## Getepay Payment Extension for Magento 2

This extension utilizes Getepay API and provides seamless integration with Magento, allowing payments for Indian merchants via Credit Cards, Debit Cards, Net Banking, and Wallets without redirecting away from the magento site.

### Installation

## **Install through the "code.zip" file**
### `bin/magento` is executable command, this is to be executed from Magento installation directory.

1. Extract the attached code.zip
2. Go to the "app" folder
3. Overwrite content of the "code" folder with step one "code" folder (Note: if the code folder does not exist just place the code folder from step 1).
4. Run following command to enable Getepay Magento module: 
```
bin/magento module:enable Getepay_Getepe
```
5. Run following command to install Magento cron jobs : 
```
bin/magento cron:install
```
6. Run `bin/magento setup:di:compile` to compile dependency code. 
7. Run `bin/magento setup:upgrade` to upgrade the Getepay Magento module from the Magento installation folder.
8. On the Magento admin dashboard, open Getepay payment method settings and click on the Save Config button.

**Note**: If you see this message highlighted in yellow (One or more of the Cache Types are invalidated: Page Cache. Please go to Cache Management and refresh cache types.) on top of the Admin page, please follow the steps mentioned and refresh the cache.
9. Run `bin/magento cache:flush` once again.

### **OR**

Install the extension through composer package manager.

```bash
composer require getepay/getepe
php bin/magento module:enable Getepay_Getepe --clear-static-content
```
You can check if the module has been installed using `bin/magento module:status`

You should be able to see `Getepay_Getepe` in the module list

#### Execute following commands from Magento installation directory:
```bash
php bin/magento setup:di:compile && php bin/magento setup:upgrade && php bin/magento setup:static-content:deploy -f && php bin/magento indexer:reindex && php bin/magento cache:flush
```

Enable and configure Getepay in Magento Admin under `Stores -> Configuration -> Payment Methods -> Getepay Payment Gateway`.

If you do not see Getepay in your gateway list, please clear your Magento Cache from your admin
panel (System -> Cache Management).

### Setting up the cron with Magento
Setup cron with Magento to execute Getepay cronjobs for following actions:

#### Cancel pending orders
It will cancel order created by Getepay as per timeout saved in configuration if `Pending Orders Cron` is Enabled.

#### Update order to processing
Check response from Getepay for events `pending` payments after order and updates pending order to processing if `Enable Update Order Cron V1` is Enabled.

#### Magento cron can be installed using following command:
```bash
bin/magento cron:install
```

## Configuration

  - **Enabled:** Mark this as "Yes" to enable this plugin.
 
  - **Title:** Test to be shown to user during checkout. For example: "Pay using GetePay"

  - **Checkout Label:** This is the label users will see during checkout, its default value is "Pay using Getepay". You can change it to something more generic like "Pay using Credit/Debit Card or Online Banking".

## Uninstall OR Rollback to older versions
To rollback, you will be required to uninstall existing version and install a new version again. Following are actions used for rollback & reinstall:

### Uninstall Getepay Module
**If composer is used for installation, use following commands from Magento installation directory to uninstall Getepay Magento module** 
```
php bin/magento module:disable Getepay_Getepe
php bin/magento module:uninstall Getepay_Getepe
```

**If code.zip is used for installation, to uninstall following steps can be used:**
Disabled Getepay Magento module
```
php bin/magento module:disable Getepay_Getepe
```

To remove module directory, execute following command from Magento install directory
```
rm -rf app/code/Getepay
```

Remove module schema from MYSQL database
```
DELETE FROM `setup_module` WHERE `setup_module`.`module` = 'Getepay_Getepe';
```

## Support

For any issue send us an email to support@getepay.in and share the `getepe.log` file. The location of `getepe.log` file is `var/log/getepe.log`.