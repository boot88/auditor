<?php

namespace App\Services\Audit;

use Exception;
use Symfony\Component\Process\Process;

class PlaywrightRenderService
{
    public function render(string $url): array
    {
        $node = $this->findNodeBinary();
        $script = base_path('scripts/render.mjs');

        if (!file_exists($script)) {
            throw new Exception('Playwright script not found: scripts/render.mjs');
        }

        $env = array_merge($_ENV, [
            'AUDIT_PLAYWRIGHT_TIMEOUT_MS' => (string) env('AUDIT_PLAYWRIGHT_TIMEOUT_MS', 25000),
            'AUDIT_MAX_HTML_BYTES' => (string) env('AUDIT_MAX_HTML_BYTES', 2500000),
        ]);

        $process = new Process([$node, $script, $url], base_path(), $env, null, 120);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput());
            $out = trim($process->getOutput());
            $msg = $err ?: $out ?: 'Playwright render failed';
            throw new Exception('Render error: ' . $msg);
        }

        $json = trim($process->getOutput());
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new Exception('Render output is not valid JSON.');
        }
        if (!empty($data['error'])) {
            throw new Exception('Render error: ' . $data['error']);
        }

        return $data;
    }

    private function findNodeBinary(): string
    {
        // Most servers have node in PATH
        $node = env('NODE_BINARY', 'node');
        return $node;
    }
}
