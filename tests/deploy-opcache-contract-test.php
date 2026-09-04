<?php

$root = dirname(__DIR__);
$workflow = (string) file_get_contents($root . '/.github/workflows/deploy-plugin.yml');

function deploy_opcache_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

deploy_opcache_assert(
    strpos($workflow, 'opcache_reset()') !== false,
    'el deploy debe invalidar OPcache dentro del pool FPM del sitio'
);
deploy_opcache_assert(
    strpos($workflow, '--resolve flacso.edu.uy:443:127.0.0.1') !== false,
    'el reset debe ejecutarse contra el vhost local de flacso.edu.uy'
);
deploy_opcache_assert(
    strpos($workflow, 'OPCACHE_RESET_URL=') !== false
        && strpos($workflow, 'RUNTIME_CHECK_URL=') !== false,
    'el reset y la verificacion deben ocurrir en dos peticiones FPM separadas'
);
deploy_opcache_assert(
    strpos($workflow, 'reset_status="$(local_request') !== false
        && strpos($workflow, 'runtime_status="$(local_request') !== false,
    'el workflow debe ejecutar primero el reset y luego un request de runtime independiente'
);
deploy_opcache_assert(
    strpos($workflow, 'method_exists(\'FLACSO_Academic_Team_Admin\', \'render_member_row\')') !== false
        || strpos($workflow, 'method_exists("FLACSO_Academic_Team_Admin", "render_member_row")') !== false,
    'el smoke test debe comprobar render_member_row'
);
deploy_opcache_assert(
    strpos($workflow, 'test ! -e "$TARGET/$OPCACHE_RESET_REL"') !== false
        && strpos($workflow, 'test ! -e "$TARGET/$RUNTIME_CHECK_REL"') !== false,
    'los endpoints temporales deben eliminarse al finalizar'
);

echo "OK deploy-opcache-contract-test\n";
