<?php
/**
 * HabboretroV14 — Loader du client Shockwave (Kepler v14)
 * Host DYNAMIQUE : tout est calé sur l'hôte utilisé pour ouvrir la page
 * (localhost en local, IP Hamachi pour les joueurs distants) — rien à modifier.
 * Login natif par défaut ; SSO supporté via ?sso=<ticket> (déconseillé : ce
 * client v14 ne sauvegarde pas la tenue quand il est auto-connecté par SSO).
 */
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';   // ex. "localhost" ou "25.x.x.x"
$host = preg_replace('/:\d+$/', '', $httpHost);      // hôte sans port (socket jeu)
$base = 'http://' . $httpHost;                        // base HTTP des assets
$sso  = isset($_GET['sso']) ? preg_replace('/[^a-f0-9]/i', '', (string)$_GET['sso']) : '';
?><html xmlns='http://www.w3.org/1999/xhtml'>
<head>
<meta http-equiv='Content-Type' content='text/html; charset=utf-8' />
<title>Kepler</title>
</head>

<body bgcolor='black'>
<div align='center'>
<object classid='clsid:166B1BCA-3F9C-11CF-8075-444553540000' codebase='http://download.macromedia.com/pub/shockwave/cabs/director/sw.cab#version=10,8,5,1,0' id='habbo' width='720' height='540'>
<param name='src' value='<?php echo $base; ?>/dcr/14.1_b8/habbo.dcr'>
<param name='swRemote' value='swSaveEnabled='true' swVolume='true' swRestart='false' swPausePlay='false' swFastForward='false' swTitle='Habbo Hotel' swContextMenu='true' '>
<param name='swStretchStyle' value='none'>
<param name='swText' value=''>
<param name='bgColor' value='#000000'>
<?php if ($sso !== '') { ?>
<param name='sw6' value='use.sso.ticket=1;sso.ticket=<?php echo $sso; ?>'>
<?php } ?>
<param name='sw2' value='connection.info.host=<?php echo $host; ?>;connection.info.port=12321'>
<param name='sw4' value='connection.mus.host=<?php echo $host; ?>;connection.mus.port=12322'>
<param name='sw3' value='client.reload.url=<?php echo $base; ?>/client.php'>
<param name='sw1' value='site.url=<?php echo $base; ?>;url.prefix=<?php echo $base; ?>'>
<param name='sw5' value='external.variables.txt=<?php echo $base; ?>/dcr/14.1_b8/external_variables.php;external.texts.txt=<?php echo $base; ?>/dcr/14.1_b8/external_texts.txt'>
<embed src='<?php echo $base; ?>/dcr/14.1_b8/habbo.dcr' bgColor='#000000' width='720' height='540' swRemote='swSaveEnabled='true' swVolume='true' swRestart='false' swPausePlay='false' swFastForward='false' swTitle='Habbo Hotel' swContextMenu='true'' swStretchStyle='none' swText='' type='application/x-director' pluginspage='http://www.macromedia.com/shockwave/download/'
<?php if ($sso !== '') { ?>
sw6='use.sso.ticket=1;sso.ticket=<?php echo $sso; ?>'
<?php } ?>
sw2='connection.info.host=<?php echo $host; ?>;connection.info.port=12321'
sw4='connection.mus.host=<?php echo $host; ?>;connection.mus.port=12322'
sw3='client.reload.url=<?php echo $base; ?>/client.php'
sw1='site.url=<?php echo $base; ?>;url.prefix=<?php echo $base; ?>'
sw5='external.variables.txt=<?php echo $base; ?>/dcr/14.1_b8/external_variables.php;external.texts.txt=<?php echo $base; ?>/dcr/14.1_b8/external_texts.txt'></embed>
</object>
</div>
</body>
</html>
