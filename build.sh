#!/bin/bash

if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

php build.php
chmod +x ./insightly-cli.phar
mv insightly-cli.phar /usr/bin/isc
rm insightly-cli.phar.gz
