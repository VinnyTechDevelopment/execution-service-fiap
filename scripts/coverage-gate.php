<?php

/**
 * Gate de cobertura do CI: falha se a cobertura de linhas (statements do
 * Clover) ficar abaixo do mínimo exigido pela Fase 4 (80% por serviço).
 *
 * Uso: php scripts/coverage-gate.php coverage.xml 80
 */
[$script, $file, $minimum] = $argv + [null, 'coverage.xml', '80'];

if (!is_file($file)) {
    fwrite(STDERR, "Relatorio de cobertura nao encontrado: {$file}\n");
    exit(1);
}

$xml = simplexml_load_file($file);
$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "Relatorio Clover invalido: {$file}\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $statements > 0 ? round($covered / $statements * 100, 2) : 0.0;

echo "Cobertura de linhas: {$percent}% ({$covered}/{$statements}) — minimo exigido: {$minimum}%\n";

if (getenv('GITHUB_STEP_SUMMARY')) {
    file_put_contents(getenv('GITHUB_STEP_SUMMARY'), "### Cobertura de testes\n\n**{$percent}%** das linhas ({$covered}/{$statements}) — minimo: {$minimum}%\n", FILE_APPEND);
}

exit($percent >= (float) $minimum ? 0 : 1);
