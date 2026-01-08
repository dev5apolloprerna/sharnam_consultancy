<?php
// dd('hello');
$root = $_SERVER['DOCUMENT_ROOT'];
$file = file_get_contents('https://getdemo.in/lalachiki/mailers/contactemail.html', 'r');
//$file = file_get_contents("https://getdemo.in/mas_solutions/mailers/welcome-company.html", "r");
$file = str_replace('#name', $contact['name'], $file);
$file = str_replace('#email', $contact['email'], $file);
$file = str_replace('#mobile', $contact['mobileNumber'], $file);
$file = str_replace('#message', $contact['message'], $file);
echo $file;

?>
