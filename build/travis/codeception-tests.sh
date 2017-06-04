#!/usr/bin/env bash
# Codeception system tests setup

set -e

BASE="$1"
cd $BASE

# Initial setup and fixes
sudo apt-get update -qq
sudo apt-get install -y --force-yes apache2 libapache2-mod-fastcgi > /dev/null
sudo mkdir $BASE/.run
chmod a+x $BASE/build/travis/codeception-tests-adjustments.sh
sudo $BASE/build/travis/codeception-tests-adjustments.sh $USER $(phpenv version-name)

# Google Chrome
sudo apt-get install chromium-chromedriver
ls -la /usr/lib/chromium-browser

# Apache setup
sudo a2enmod rewrite actions fastcgi alias
echo "cgi.fix_pathinfo = 1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm
sudo cp -f $BASE/build/travis/apache2/php-apache-codeception /etc/apache2/sites-available/default
sudo sed -e "s?%TRAVIS_BUILD_DIR%?$BASE?g" --in-place /etc/apache2/sites-available/default
sudo sed -e "s?%PHPVERSION%?${TRAVIS_PHP_VERSION:0:1}?g" --in-place /etc/apache2/sites-available/default
git submodule update --init --recursive
sudo service apache2 restart

# Forcing localhost in hosts file
sudo sed -i '1s/^/127.0.0.1 localhost\n/' /etc/hosts

# Xvfb
export DISPLAY=:99.0
sh -e /etc/init.d/xvfb start &
sleep 3 # give xvfb some time to start

# Fluxbox
sudo apt-get install fluxbox -y --force-yes
fluxbox &
sleep 3 # give fluxbox some time to start

# Composer in tests folder
cd tests/codeception
composer install
cd $BASE

sudo cp $BASE/tests/codeception/JoomlaTesting.dist.ini $BASE/tests/codeception/JoomlaTesting.ini
sudo cp $BASE/tests/codeception/acceptance.suite.dist.yml $BASE/tests/codeception/acceptance.suite.yml
