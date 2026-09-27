<?php
/**
 * external_variables DYNAMIQUE — pour Hamachi / accès distant.
 * Reprend external_variables.txt et remplace localhost par l'hôte réellement
 * utilisé par le joueur (localhost en local, IP Hamachi pour les distants),
 * pour que TOUS les assets (dcr, c_images, gamedata) se chargent chez lui.
 */
header('Content-Type: text/plain');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$txt = @file_get_contents(__DIR__ . '/external_variables.txt');
if ($txt === false) { http_response_code(500); exit; }
echo str_replace(
    ['http://localhost/', 'http://127.0.0.1/'],
    'http://' . $host . '/',
    $txt
);
