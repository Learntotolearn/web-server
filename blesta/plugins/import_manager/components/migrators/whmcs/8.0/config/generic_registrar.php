<?php
Configure::set('generic_registrar.map', [
    'module' => 'generic_domains',
    'module_row_meta' => [],
    'package_meta' => [
        (object)['key' => 'tlds', 'value' => 'tlds', 'serialized' => 1, 'encrypted' => 0]
    ],
    'service_fields' => [
        'domain' => (object)['key' => 'domain', 'serialized' => 0, 'encrypted' => 0]
    ]
]);
