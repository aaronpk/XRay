<?php
echo "Enter a password: ";
hide_term();
$password1 = trim(fgets(STDIN), PHP_EOL);
echo PHP_EOL;
echo "Confirm password: ";
$password2 = trim(fgets(STDIN), PHP_EOL);
echo PHP_EOL;
restore_term();

if($password1 == $password2) {
  $hash = password_hash($password1, PASSWORD_DEFAULT);
  echo "Password hash: $hash\n";
} else {
  echo "Passwords did not match\n";
  die(1);
}

function hide_term() {
  system('stty -echo');
}

function restore_term() {
  system('stty echo');
}

