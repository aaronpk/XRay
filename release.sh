#!/bin/bash

release_dir=../XRay-release

current=`pwd`

composer install

rm $release_dir/xray-app.zip
rsync -ap --delete controllers $release_dir/
rsync -ap --delete lib $release_dir/
rsync -ap --delete public $release_dir/
rsync -ap --delete views $release_dir/
rsync -ap --delete --exclude=.git vendor $release_dir/
cp README.md $release_dir/
cp LICENSE.txt $release_dir/
cp index.php $release_dir/
cp .htaccess $release_dir/
cp controllers/.htaccess $release_dir/vendor/

cd $release_dir
zip -r xray-app.zip .

cd $current
