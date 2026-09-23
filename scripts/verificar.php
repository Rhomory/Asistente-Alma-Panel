<?php
// Verificación rápida de cifras canónicas (se ejecuta con: php scripts/verificar.php)
$db = new PDO('sqlite:' . __DIR__ . '/../database/database.sqlite');
$q = fn (string $sql) => $db->query($sql)->fetch(PDO::FETCH_ASSOC);

$inicio = $q("SELECT * FROM reporte_pagina WHERE nombre='Inicio' AND proyecto_id=1");
$tot = $q('SELECT SUM(min_asistente) a, SUM(min_dev) d FROM secciones');
$n = $q('SELECT COUNT(*) c FROM secciones');

printf("Secciones registradas: %d\n", $n['c']);
printf("Inicio (Bodega): %d min total | asistente %d | dev %d | correcciones %d\n",
    $inicio['min_total'], $inicio['min_asistente'], $inicio['min_dev'], $inicio['correcciones']);
printf("Inicio vs línea base: -%d%%\n", round(100 - 100 * $inicio['min_total'] / $inicio['linea_base_min']));
printf("Reparto Inicio: %d%% asistente\n", round(100 * $inicio['min_asistente'] / ($inicio['min_asistente'] + $inicio['min_dev'])));
printf("Journal mode: %s\n", $db->query('PRAGMA journal_mode')->fetchColumn());
