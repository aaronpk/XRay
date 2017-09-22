#!/bin/bash

release_dir=../XRay-library-release

current=`pwd`

composer install --no-dev

rm $release_dir/xray-library.zip
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
zip -r xray-library.zip .

cd $current
