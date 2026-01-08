<?php
{{--  dd($data);  --}}
$root = $_SERVER['DOCUMENT_ROOT'];
$file = file_get_contents($root . '/mailers/forgetpassword.html', 'r');
$file = str_replace('#email', 'http://127.0.0.1:8000/New-Password/' . $data['token'] , $file);

{{--  dd($file);  --}}

echo $file;

?>
