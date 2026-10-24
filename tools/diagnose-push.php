<?php

echo 'PHP: '.PHP_VERSION.PHP_EOL;
echo 'OpenSSL: '.OPENSSL_VERSION_TEXT.PHP_EOL;
echo 'OPENSSL_CONF: '.(getenv('OPENSSL_CONF') ?: '(unset)').PHP_EOL;
foreach ([null, dirname(PHP_BINARY).'/extras/ssl/openssl.cnf'] as $config) {
    $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    if ($config !== null) {
        $options['config'] = $config;
    }
    while (openssl_error_string() !== false) {
    }
    $key = openssl_pkey_new($options);
    $export = $key !== false && openssl_pkey_export($key, $pem, null, $options);
    echo ($config ?? 'default').': generation='.($key !== false ? 'ok' : 'failed').', export='.($export ? 'ok' : 'failed').PHP_EOL;
    while ($error = openssl_error_string()) {
        echo $error.PHP_EOL;
    }
}
