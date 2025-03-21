# nttdatapay-magento
 
## Introduction
This is a Magento code which will help you to integrate NTT DATA Payment Gateway into your Magento application.

## Prerequisites
- Magento (2.3.x or above)
 
## Installation 
1. Download Ndps plugin for Magento.
2. Extract the zip and navigate to the "app" directory.
3. If a "code" folder exists, overwrite its contents with the "code" folder from the zip file. If it does not exist, simply place the new "code" folder in the app directory.
4. Execute the following commands in your Magento root folder to enable the Ndps Aipay module:

		php bin/magento module:enable Ndps_Aipay
		php bin/magento setup:upgrade
		
    Check if the module is installed with:

	    php bin/magento module:status
		
    `Ndps_Aipay` should appear in your module list.


## Configuration
Configure the Ndps payment method in your Magento Admin:
- Navigate to **Admin** -> **Stores** -> **Configuration** -> **Sales** -> **Payment Method** -> **Ndps**.

Try clearing your Magento Cache from your admin panel if you experience any issues:
- Go to **System** -> **Cache Management** in the admin panel. 


 To solve the session loss issue after transaction completion, you need to set the SameSite cookie to none in your apache/ngnix configuration.

For Apache, you can follow below 
 - Go to apache -> conf -> httpd.conf file and enter below line:
 
     Header edit Set-Cookie ^(.*)$ "$1; Secure; SameSite=None"
	
 - Save file and restart Apache. If it is not working then you may need to restart your system/server.
 - You can check your cookies section with the steps below:
 - Go to Developer tools (right click and select inspect on the website) -> Application -> Cookies -> Select your website and see for SameSite none value is set or not.


