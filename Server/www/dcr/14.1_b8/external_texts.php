<?php
/**
 * external_texts DYNAMIQUE — pour Hamachi / accès distant (habbov14.test, IP…).
 * Reprend external_texts.txt et remplace localhost par l'hôte réellement utilisé
 * par le joueur, pour que les liens intégrés (ex. chatlog.url « Voir le journal
 * de discussion » de l'alerte modérateur) pointent vers le bon hôte, et pas
 * toujours vers localhost. Renvoie les octets bruts (accents CP1252 préservés).
 */
header('Content-Type: text/plain');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// SEC05 : HTTP_HOST est contrôlable par le client -> on le restreint à un hôte
// valide (repli localhost) pour empêcher une injection via l'en-tête Host.
if (!preg_match('/^[A-Za-z0-9.\-:]+$/', $host)) $host = 'localhost';
$txt = @file_get_contents(__DIR__ . '/external_texts.txt');
if ($txt === false) { http_response_code(500); exit; }
echo str_replace(
    ['http://localhost/', 'http://127.0.0.1/'],
    'http://' . $host . '/',
    $txt
);
