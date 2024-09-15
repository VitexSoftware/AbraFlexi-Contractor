![project logo](project-logo.png?raw=true)

Customize and Print you AbraFlexi contracts
=========================================

[![wakatime](https://wakatime.com/badge/user/5abba9ca-813e-43ac-9b5f-b1cfdf3dc1c7/project/ee6a134c-910e-437e-89ad-c357ea37af50.svg)](https://wakatime.com/badge/user/5abba9ca-813e-43ac-9b5f-b1cfdf3dc1c7/project/ee6a134c-910e-437e-89ad-c357ea37af50)

Customize and Print your AbraFlexi Contracts 

Usage
-----



Installation
------------

In the browser, you need to manually open the [install.php](src/install.php) page.

Fill in the access credentials for your AbraFlexi in the form.
If they are correct, a trigger button will be created in AbraFlexi's received invoices.

(If the server and port auto-detection fails, please copy the value from the address bar to the corresponding field)

The running application can be tested at [contractor.vitexsoftware.com](https://contractor.vitexsoftware.com/)

Testing
-------

If the page is not called with the $authSessionId && $companyUrl parameters, it will attempt to load the configuration file ../testing/.env

Deployment
----------

K dispozici je Docker image: https://hub.docker.com/r/vitexsoftware/abraflexi-contractor/tags

```
docker run -d -p ${OUTPORT}:${INPORT} --name ${CONTNAME} vitexsoftware/abraflexi-contractor
```

Or a Debian package for installation on a server with Debian or Ubuntu system:


```
sudo apt install lsb-release wget apt-transport-https bzip2

sudo wget -O /usr/share/keyrings/vitexsoftware.gpg https://repo.vitexsoftware.cz/keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update

sudo apt install abraflexi-contractor
```


```shell
```

If you are using Apache, you need to activate its configuration:

```
a2enconf abraflexi-contractor
apache2ctl restart
```

After that, the application is available without any further configuration at http://0.0.0.0/abraflexi-contractor/


![Installer](installer.png?raw=true)
