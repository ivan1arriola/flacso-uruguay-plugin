<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function flacso_module_assert($condition, string $message): void {
    static $count = 0;
    $count++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $GLOBALS['flacso_module_assertions'] = $count;
}

$plugin_main = file_get_contents($root . '/flacso-uruguay.php');
flacso_module_assert($plugin_main !== false, 'flacso-uruguay.php must be readable');

// 1. Verify module is loaded in flacso-uruguay.php
flacso_module_assert(
    strpos($plugin_main, "\$loader->load_module('preinscripciones');") !== false,
    "flacso-uruguay.php must load the 'preinscripciones' module"
);

// 2. Verify load order: after seminarios and oferta-academica
$pos_seminarios = strpos($plugin_main, "\$loader->load_module('seminarios');");
$pos_oferta = strpos($plugin_main, "\$loader->load_module('oferta-academica');");
$pos_preinscripciones = strpos($plugin_main, "\$loader->load_module('preinscripciones');");

flacso_module_assert($pos_seminarios !== false, "seminarios module is loaded");
flacso_module_assert($pos_oferta !== false, "oferta-academica module is loaded");
flacso_module_assert(
    $pos_preinscripciones > $pos_seminarios && $pos_preinscripciones > $pos_oferta,
    "preinscripciones module must be loaded after seminarios and oferta-academica"
);

// 3. Verify init.php exists and references all 5 module classes
$init_file = $root . '/modules/preinscripciones/init.php';
flacso_module_assert(file_exists($init_file), 'modules/preinscripciones/init.php must exist');
$init_content = file_get_contents($init_file);

$expected_classes = [
    'class-preinscriptions-field-catalog.php',
    'class-preinscriptions-config.php',
    'class-preinscriptions-meta.php',
    'class-preinscriptions-admin.php',
    'class-preinscriptions-serializer.php',
    'class-preinscriptions-rest.php',
];
foreach ($expected_classes as $cls_file) {
    flacso_module_assert(
        strpos($init_content, $cls_file) !== false,
        "init.php must load {$cls_file}"
    );
}

// 4. Verify API.md documents the public v1 endpoint
$api_md = file_get_contents($root . '/API.md');
flacso_module_assert($api_md !== false, 'API.md must be readable');
flacso_module_assert(
    strpos($api_md, '/wp-json/flacso/v1/preinscripciones') !== false,
    'API.md must document GET /wp-json/flacso/v1/preinscripciones'
);

// 5. Verify CHANGELOG.md contains the preinscriptions entry
$changelog_md = file_get_contents($root . '/CHANGELOG.md');
flacso_module_assert($changelog_md !== false, 'CHANGELOG.md must be readable');
flacso_module_assert(
    stripos($changelog_md, 'preinscripciones') !== false,
    'CHANGELOG.md must document the preinscripciones integration'
);

printf("PASS: preinscriptions module integration contract (%d assertions)\n", $GLOBALS['flacso_module_assertions'] ?? 0);
