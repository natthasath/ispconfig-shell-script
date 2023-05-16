# ISPConfig 3 AutoInstaller
![alt text](https://www.ispconfig.org/wp-content/themes/ispconfig/images/ispconfig_logo.png "") \
[![pipeline status](https://git.ispconfig.org/ispconfig/ispconfig-autoinstaller/badges/master/pipeline.svg)](https://git.ispconfig.org/ispconfig/ispconfig-autoinstaller/commits/master)   

This script configures your server (Ubuntu 18.04, Ubuntu 20.04, Debian 9, 10 and 11 currently) following the "perfect server tutorials" from howtoforge.com and installs ISPConfig 3.2. It currently supports the x86_64 (also known as AMD64) CPU architecture only while ARM is not supported.

## Using the script
You can use the script with curl  
`curl https://get.ispconfig.org | sh`  
or with wget  
`wget -O - https://get.ispconfig.org | sh`

You can also use the git repository for installing:  
```bash
cd /tmp
git clone https://git.ispconfig.org/ispconfig/ispconfig-autoinstaller.git
cd ispconfig-autoinstaller
./ispc3-ai.sh
```

## Providing arguments to the installer
If you need to customize the install process you can provide arguments to the installer script. For example, if you want to enable debug logging and don't need mailman on your server:  
`curl https://get.ispconfig.org | sh -s -- --debug --no-mailman`  
or using wget  
`wget -O - https://get.ispconfig.org | sh -s -- --debug --no-mailman`

If you checked out the installer from git you can simply pass the arguments to the script itself:  
`./ispc3-ai.sh --debug --no-mailman`

To see all available arguments, please provide the `--help` argument:  
`curl https://get.ispconfig.org | sh -s -- --help`  
or using wget  
`wget -O - https://get.ispconfig.org | sh -s -- --help`
