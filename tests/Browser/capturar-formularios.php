<?php

// Capturas limpias del mismo HTML de testing, sin ejecutar el guion de interacciones.
$directorio = realpath($argv[1] ?? '');
$chrome = $argv[2] ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
$proyecto = str_replace('\\', '/', realpath(dirname(__DIR__, 2)));
if (! $directorio || str_starts_with(strtolower(str_replace('\\', '/', $directorio)), strtolower($proyecto).'/') || ! is_file($chrome)) {
    throw new RuntimeException('Se requiere una carpeta externa de evidencias y Chrome existente.');
}
$html = file_get_contents($directorio.'/entrega.html');
$html = preg_replace('~<script defer src="[^"]*/comprobar-selectores\.js"></script>~', '', $html);
if (str_contains($html, 'comprobar-selectores.js') || ! str_contains($html, 'e.preventDefault()')) {
    throw new RuntimeException('El HTML debe conservar el bloqueo de envíos y retirar solo el guion automático.');
}
file_put_contents($directorio.'/entrega-captura.html', $html);
$capturas = [];
foreach ([[1440, 1000], [500, 844]] as [$ancho, $alto]) {
    $nombre = 'entrega-limpia-'.$ancho;
    $proceso = proc_open([
        $chrome, '--headless=new', '--disable-gpu', '--no-first-run', '--allow-file-access-from-files',
        '--user-data-dir='.$directorio.'/perfil-captura-'.$ancho, '--window-size='.$ancho.','.$alto,
        '--virtual-time-budget=2500', '--screenshot='.$directorio.'/'.$nombre.'.png',
        'file:///'.str_replace('\\', '/', $directorio).'/entrega-captura.html',
    ], [0 => ['pipe', 'r'], 1 => ['file', $directorio.'/'.$nombre.'.log', 'w'], 2 => ['file', $directorio.'/'.$nombre.'-chrome.log', 'w']], $pipes);
    if (! is_resource($proceso)) {
        throw new RuntimeException('No se pudo iniciar Chrome.');
    }
    fclose($pipes[0]);
    $codigo = proc_close($proceso);
    $archivo = $directorio.'/'.$nombre.'.png';
    $dimensiones = is_file($archivo) ? getimagesize($archivo) : false;
    if ($codigo !== 0 || ! $dimensiones || $dimensiones[0] !== $ancho || $dimensiones[1] !== $alto) {
        throw new RuntimeException('La captura no tiene las dimensiones esperadas: '.$nombre);
    }
    $capturas[] = ['archivo' => $nombre.'.png', 'ancho' => $ancho, 'alto' => $alto, 'sha256' => hash_file('sha256', $archivo)];
}
file_put_contents($directorio.'/capturas.json', json_encode($capturas, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['capturas' => count($capturas), 'aceptacion_humana' => false], JSON_THROW_ON_ERROR).PHP_EOL;
