#!/bin/bash

urls=(
  'https://www.instagram.com/aaronpk/'
  'https://www.instagram.com/indiewebcat/'
  'https://www.instagram.com/kmikeym/'
  'https://www.instagram.com/microformats/'
  'https://www.instagram.com/pk_spam/'
  'https://www.instagram.com/p/BO5rYVElvJq/'
  'https://www.instagram.com/p/BGDpqNoiMJ0/'
  'https://www.instagram.com/p/BO_RN8AFZSx/'
  'https://www.instagram.com/p/BNfqVfVlmkj/'
  'https://www.instagram.com/p/BN3Z5salSys/'
  'https://www.instagram.com/p/BZWmUB_DVtp/'
  'https://www.instagram.com/p/BZWmpecjBwN/'
  'https://www.instagram.com/explore/locations/109284789535230/'
  'https://www.instagram.com/explore/locations/359000003/'
  'https://www.instagram.com/p/BsdjKytBZyx/'
  'https://www.instagram.com/p/BsdlOmLh_IX/'
)

for url in ${urls[@]}; do
  fn=$(echo $url | sed 's#https://www.instagram.com/##' | sed 's#/#_#g')
  echo "$url > $fn"
  curl -i -s $url > data/www.instagram.com/$fn
  unix2dos data/www.instagram.com/$fn
  sleep 2
done

