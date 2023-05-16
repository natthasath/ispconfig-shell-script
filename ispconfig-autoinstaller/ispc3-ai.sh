#!/usr/bin/env sh

CURDIR=$(pwd) ;

BASEDIR=$(dirname $0) ;
BASEDIR=$(realpath ${BASEDIR}) ;

CURL=$(which curl) ;
WGET=$(which wget) ;
PHP=$(which php) ;
APT=$(which apt-get) ;

use_git=false;
if [ -d .git ]; then
  use_git=true;
fi

if [ "$APT" = "" ] ; then
	echo "It seems you are using a distribution that has no apt-get available. This is not supported.";
	exit 1 ;
fi

if [ "$CURL" = "" ] ; then
	if [ "$WGET" = "" ] ; then
		echo "Curl and Wget missing, trying to install." ;
		apt-get update -qq && apt-get -y -qq install wget;
		WGET=$(which wget) ;
	fi
	if [ "$WGET" = "" ] ; then
		echo "Wget and curl are missing. Please install either wget or curl package." ;
		exit 1 ;
	fi
fi

if [ "$PHP" = "" ] ; then
	echo "PHP cli missing, trying to install." ;
	apt-get update -qq && apt-get -y -qq install php-cli && apt-get -y -qq install php-mbstring ;
	PHP=$(which php) ;
fi
if [ "$PHP" = "" ] ; then
	echo "PHP cli is missing. Please install package php-cli." ;
	exit 1;
fi

INSTALL_DIR=".";
if [ "$use_git" = false ] ; then

	if [ "$CURL" != "" ] ; then
		$CURL -s -o /tmp/ispconfig-ai.tar.gz "https://www.ispconfig.org/downloads/ispconfig-ai.tar.gz" >/dev/null 2>&1
	else
		$WGET -q -O /tmp/ispconfig-ai.tar.gz "https://www.ispconfig.org/downloads/ispconfig-ai.tar.gz" >/dev/null 2>&1
	fi

	if [ ! -f "/tmp/ispconfig-ai.tar.gz" ] ; then
		echo "Failed downloading Autoinstaller" ;
		exit 1;
	fi

	rm -rf /tmp/ispconfig-ai ;
	mkdir /tmp/ispconfig-ai ;
	tar -C /tmp/ispconfig-ai/ -xzf /tmp/ispconfig-ai.tar.gz || (echo "Failed extracting Autoinstaller" ; exit 1)
	rm -f /tmp/ispconfig-ai.tar.gz ;
	cd /tmp/ispconfig-ai ;
	INSTALL_DIR="/tmp/ispconfig-ai";
fi

TTY=$(ps ax | grep "^[ ]*"$$ | head -n 1 | awk '{ print $2 }' 2>/dev/null);
if [ "$TTY" != "" ] ; then
	${PHP} -q "$INSTALL_DIR/ispconfig.ai.php" $@ < /dev/${TTY} ;
else 
	echo "It seems you are not using a TTY. Please add --i-know-what-i-am-doing to the arguments.";
	${PHP} -q "$INSTALL_DIR/ispconfig.ai.php" $@ ;
fi

cd ${CURDIR} ;
