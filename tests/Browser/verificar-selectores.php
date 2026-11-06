<?php

// Prueba de navegador sobre HTML exportado desde testing; no conecta a ninguna BD.
$directorio = realpath($argv[1] ?? '');
$chrome = $argv[2] ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
$proyecto = str_replace('\\', '/', realpath(dirname(__DIR__, 2)));
if (! $directorio || str_starts_with(strtolower(str_replace('\\', '/', $directorio)), strtolower($proyecto).'/') || ! is_file($chrome)) {
    throw new RuntimeException('Indica una carpeta de evidencias externa y un ejecutable Chrome existente.');
}
copy(__DIR__.'/selectores.js', $directorio.'/comprobar-selectores.js');
$resultados = [];
foreach ([['entrega', 1440, 1000], ['entrega', 500, 844], ['remito', 1440, 1000], ['ajuste', 1440, 1000], ['unidad', 1440, 1000]] as [$escenario, $ancho, $alto]) {
    if (! is_file($directorio.'/'.$escenario.'.html')) {
        throw new RuntimeException('Primero exporta los formularios mediante SearchableSelectorsTest.');
    }
    $nombre = $escenario.'-'.$ancho;
    $proceso = proc_open([
        $chrome, '--headless=new', '--disable-gpu', '--no-first-run', '--allow-file-access-from-files',
        '--user-data-dir='.$directorio.'/perfil-php-'.$nombre,
        '--window-size='.$ancho.','.$alto, '--virtual-time-budget=5000', '--dump-dom',
        '--screenshot='.$directorio.'/'.$nombre.'.png',
        'file:///'.str_replace('\\', '/', $directorio).'/'.$escenario.'.html',
    ], [0 => ['pipe', 'r'], 1 => ['file', $directorio.'/'.$nombre.'-dom.html', 'w'], 2 => ['file', $directorio.'/'.$nombre.'-chrome.log', 'w']], $pipes);
    if (! is_resource($proceso)) {
        throw new RuntimeException('No se pudo abrir Chrome.');
    }
    fclose($pipes[0]);
    $codigo = proc_close($proceso);
    $html = file_get_contents($directorio.'/'.$nombre.'-dom.html');
    if ($codigo !== 0 || ! preg_match('/<pre id="resultado-pruebas">(.*?)<\/pre>/s', $html, $match)) {
        throw new RuntimeException('Chrome no produjo resultados: '.$nombre);
    }
    $resultado = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
    $resultado['ejecucion'] = $nombre;
    $resultado['captura'] = $nombre.'.png';
    $resultados[] = $resultado;
}
file_put_contents($directorio.'/navegador-final.json', json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$fallos = array_filter($resultados, fn ($r) => ! $r['correcto']);
echo json_encode(['escenarios' => count($resultados), 'comprobaciones' => array_sum(array_map(fn ($r) => count($r['resultados']), $resultados)), 'fallos' => count($fallos)], JSON_PRETTY_PRINT).PHP_EOL;
exit($fallos ? 1 : 0);
