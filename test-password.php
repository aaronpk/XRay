<?php
echo "Enter password hash to verify against: ";
$hash = trim(fgets(STDIN), PHP_EOL);

echo "Enter password: ";
hide_term();
$password = trim(fgets(STDIN), PHP_EOL);
echo PHP_EOL;
restore_term();

$verified = password_verify($password, $hash);

if($verified) 
  echo "Password verified\n";
else
  echo "Password did not match\n";

function hide_term() {
  system('stty -echo');
}

function restore_term() {
  system('stty echo');
}
