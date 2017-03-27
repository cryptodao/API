<?php
require 'cryptodao.php';


$key = 'jpdiv-s74e0-s1hfx-liaa2-p098m-45xo7-g8xtr';
$privateKey = 'smzx0-zp2h5-02nbc-q0cz8-z3exs-me1j8-scus9';

$api = new Api_cryptodao($key, $privateKey, './secret');


print_r($api->ticker('BTC', 'LTC'));
