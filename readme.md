# Prerequisites

Requires a https://www.surveygizmo.com/ account with API access. (Use API Version 3)

Tested with OpenClinica 3.4 & 3.5

The README assumes that you are logged in to the system with a user that has sudo privileges. Following softwares/packages need to be installed:

* PHP 5.5.*
* PostgreSQL Server 8.4 and client

### Install php 5.5

Depending on your Linux flavor either use yum or apt-get to install the packages. 
(Not tested on Windows)

```
$ sudo yum install php55
```

Install PHP modules/extensions required by the SG-OC connector.

```sh
$ sudo yum install php55-xml
$ sudo yum install php55-soap
$ sudo yum install php55-pdo
$ sudo yum install php55-pgsql
```

Set appropriate timezone in php.ini. Usually the php.ini file is found in /etc/php.ini. Find the [Date] section and add a line that defines timezone appropriately:

```
date.timezone = Australia/NSW
```

### Install PostgreSQL server and command line client

We use PostgreSQL 8.4 server for this connector. Install the PostgreSQL server.

```
$ sudo yum install postgresql8 postgresql8-server postgresql8-contrib
```

Follow PostgreSQL documentation to create a user 'sgoc' with password 'sgocdbpass'. 

# Install SG-OC Connector

Unzip the provided sg-oc.zip into a folder. Rest of the README refers to this folder as BASE folder. 

### Create a DB table for SG-OC connector

Open PostgreSQL shell and import the DB table creation schema file.

```
psql -U sgoc -W -d sgoc
Password for user sgoc: 
psql (8.4.20)
Type "help" for help.

sgoc=# \i config/sql/create.sql
```

This should create the database as well as the required schema.

### Configure SG-OC connector

Open config.php file available under config/ directory. Make sure you have appropriate values for following configuration items:

	1. CONFIG_XML_FILES_PATH: path to XML config files, default path is ./config/xml
	 
	2. db_username: Your DB username
 
	3. db_password: Your DB user password
	
	4. email_SMTP_username: Email address used to send the alert emails
	
	5. email_SMTP_userpassword: Password of above email user
	
	6. alert_email_list: Email addresses of persons who should receive alerts 

### Set up Cron to invoke the SG-OC connector 

The BASE directory has a crontab.txt file. Open it in your favorite editor and
adjust the MAILTO and path appropiately. The crontab is configured to invoke the connector every 30 minutes. Issue the following command to install your cronjob.

```
$ crontab -e crontab.txt
```

You can verify if your crontab has been registered with Cron daemon by issueing following command:

```
$ crontab -l 
```

View the sample xml config file as template for configurations

Each item must specify the SGQuestionID. The optionID is used for SG question types with option text answers. e.g. Select other from check box list and add text.



SG and OC build/setup steps....notes
1. Each SG survey must contain three "Hidden Values" that are populated with URLVariables. Naming below must be used!
	- SubjectID = URLVariable  "ssid"
	- EventID = URLVariable  "eid"
	- EventNumber = URLVariable  "enum"
	
2. Access the SG survey via link in OC CRF
e.g. https://www.surveygizmo.com/s3/123456789/SURVEY-NAME?ssid=${studySubjectOID}&eid=${eventName}&enum=${eventOrdinal}

3. You can used SG custom scripting to ensure a survey has not yet been completed with identical combination of SubjecID, EventID and EventNumber. Take care as new surveys will overwrite current data in OC. (Could explore new features in OC 3.6 to help with this. Not yet implemented!)

4. Only surveys marked as "Complete" are pushed in OC. 

5. Currently DataType is ignored for all types except DATE where you must also specify the date format used by the survey question.

6. View the sample xml config file as template for new configurations

7. Each item must specify the SGQuestionID (use Survey Legend to find IDs). The optionID is used for SG question types with option text answers. e.g. Select other from check box list and add text.













