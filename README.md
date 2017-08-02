# Email Parser
<h3> What is it? </h3>
The task above suppose to do analyses on e-mail semantic . the script is using Gmail API and chop a signature off an email body. <br/>

Tips:<br/>
a) Please assume that everything under "--" is a signature<br/>
b) Name, lastname and phone number shuld be exported and write it in CVS<br/>
c) PHP Thread has been used to speed up the processes <br/>

<h3> requirements </h3>
-Php 5.6 Thread Safe / e.g 5.6.29RC1<br/>
-Download suitable version of PThread PHP from http://windows.php.net/downloads/pecl/releases/pthreads/2.0.9/ update your php.ini like it documentation http://php.net/manual/en/pthreads.installation.php<br/>
-Use https://console.developers.google.com/apis/api/gmail/overview to enable Gmail API on your side and download the Json key containing OAuth2 client ID and Client Secret, and place it in project directory and rename it to "client_secret.json" - later you can specify the path/name of it in config.inc <br/>
-If you will have the problem with CURL certification then you need to add one .<br/>
- "google/apiclient": "2.0" has been already downloaded and attached to the app. </b>

<h3> so lets go through documentation </h3>

You are almost done !<br/>
After inserting your client secret in yout application path now there is little configurations need to be done<br/>
in config.inc file you need to update the APP_PATH to the path of your application<br/>
There also you can customise the numbers of emails which will be retrived after runing the app<br/>

DONE!<br/>
just run your local verion of the application , i prefer use PHP CLI to run the php listening to port so<br/>
run php -S localhost:8080 on your local machine in the path of your local copy e.g C:\inetpub\wwwroot\NF>php -S localhost:8080<br/>

Then use your browser and enter the local environment address , you should be asked for permission ,after entering the credential<br/> there will be CSV file ready to download <br/>
CSV file contains three columns (Firstname,lastname,Telephone)<br/>
<b>the app will export email correctly if and only if the format is suitable for the app, otherwise CSV file will filled up with <i>NOT FOUND</i> statement</b>


Regards.



