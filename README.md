# Wechat_api

Following the instructions to download the scret json file:
----------------------------------------------------------
[google php api tutorial link] (https://developers.google.com/drive/web/quickstart/php)

Clone the google api for php from git:
---------------------------------------
git clone -b v1-master https://github.com/google/google-api-php-client.git

Modify the following nside the config.php: 
------------------------------------------ 
$upload_mime_type='text/csv';  
$upload_folder_id='FOLDER_ID';  
$appid='APPID';  
$appsecret='APPSECRET';  

1. Replace the APPID and APPSECRET with your own set, which you can find out in Wechat backend.  
2. Replace the FOLDER_ID with the destination google drive folder id  

Run the command:
----------------
* php wechat_api.php

1. enter the url generated
2. copy the token and paste in the command prompt

Usage: 
------   
* php wechat_api.php - collect the data yesterday
* php wechat_api.php yyyy-mm-dd - collect the data on specific day

output files would be generated to the directory "csv".
