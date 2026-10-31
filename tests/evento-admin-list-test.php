<?php
// Estado de un evento según sus fechas, usado por la lista de Eventos del admin.
define('ABSPATH', __DIR__ . '/');
function __($t, $d = '') { return $t; }
function add_filter() {}
function add_action() {}
function check($ok, $message) { if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }
require dirname(__DIR__) . '/modules/eventos/includes/admin-list.php';

$tz = new DateTimeZone('America/Montevideo');
$at = static fn(string $s): int => (new DateTimeImmutable($s, $tz))->getTimestamp();
$now = $at('2026-11-10 12:00');
$state = static fn(string $sd, string $st, string $ed, string $et) => Evento_Schedule::resolve($sd, $st, $ed, $et, $now, $tz);

check($state('', '', '', '')['state'] === 'sin_fecha', 'Sin fecha de inicio el evento no tiene estado temporal');
check($state('2026-11-12', '18:00', '', '')['state'] === 'proximo', 'Un inicio futuro es próximo');
check($state('2026-11-01', '18:00', '', '')['state'] === 'finalizado', 'Un día pasado sin fin es finalizado');
check($state('2026-11-10', '09:00', '', '')['state'] === 'en_curso', 'Sin fin ni hora de fin dura hasta el cierre del mismo día');
check($state('2026-11-09', '09:00', '2026-11-11', '')['state'] === 'en_curso', 'Un evento de varios días está en curso entre inicio y fin');
check($state('2026-11-10', '09:00', '2026-11-10', '11:00')['state'] === 'finalizado', 'Termina al llegar la hora de fin');
check($state('2026-11-12', '18:00', '2026-11-11', '10:00')['end'] === $at('2026-11-12 18:00'), 'Un fin anterior al inicio se ignora');
check($state('2026-11-12', '', '', '')['start'] === $at('2026-11-12 00:00'), 'Sin hora el inicio es a medianoche');
check(Evento_Schedule::timestamp('no-es-fecha', '', false, $tz) === null, 'Una fecha inválida no produce marca de tiempo');
echo "OK evento admin list\n";
