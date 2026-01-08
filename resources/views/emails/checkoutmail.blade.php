<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$file = file_get_contents($root . '/mailers/checkoutmail.html', 'r');

$address= $Order['shiiping_address1'] .','. $Order['shiiping_address2'];
$file = str_replace('#name', $Order['shipping_cutomerName'], $file);
$file = str_replace('#email', $Order['shipping_email'], $file);
$file = str_replace('#mobile', $Order['shipping_mobile'], $file);
$file = str_replace('#address', $address, $file);
$file = str_replace('#state', $Order['shiiping_state'], $file);
$file = str_replace('#city', $Order['shipping_city'], $file);
$file = str_replace('#pincode', $Order['shipping_pincode'], $file);
$file = str_replace('#amount', $Order['amount'], $file);
$file = str_replace('#netAmount', $Order['netAmount'], $file);
$file = str_replace('#shipping_Charges', $Order['shipping_Charges'], $file);

$html = "";
$i=1;
$iTotal = 0;
$cartItems = \Cart::getContent();

foreach ($cartItems as $cartItem) {
    {{--  dd($cartItem);  --}}
    $html .= '<tr>
        <td>'.$i.'</td>
        <td>'.$cartItem['name'].'</td>
        <td><img width="48" height="48" src=https://mbherbals.com/Product/'.$cartItem['attributes']['image'].'></td>
        <td>'.$cartItem['weight'].'</td>
        <td>'.$cartItem['quantity'].'</td>
        <td>'.$cartItem['price'].'</td>

    </tr>';
$i++;
}
$file = str_replace('#tableProductTr', $html, $file);

echo $file;
// exit;
?>
