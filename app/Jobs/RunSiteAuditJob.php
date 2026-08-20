<?php

namespace App\Jobs;

use App\Models\SiteAudit;
use App\Services\Audit\OpenAiAuditService;
use App\Services\Audit\PageExtractService;
use App\Services\Audit\PlaywrightRenderService;
use App\Services\Audit\SsrfGuard;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSiteAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $auditId) {}

    public function handle(
        SsrfGuard $ssrf,
        PlaywrightRenderService $renderer,
        PageExtractService $extractor,
        OpenAiAuditService $llm
    ): void {
        $audit = SiteAudit::find($this->auditId);
        if (!$audit) return;

        $audit->update(['status' => 'running', 'error' => null]);

        try {
            // 1) SSRF guard
            $ssrf->assertUrlAllowed($audit->url);

            // 2) Render (JS) with Playwright
            $t0 = microtime(true);
            $render = $renderer->render($audit->url);
            $renderMs = (int) round((microtime(true) - $t0) * 1000);

            $audit->final_url = $render['final_url'] ?? null;
            $audit->http_status = $render['status'] ?? null;
            $audit->render_ms = $renderMs;
            $audit->save();

            $html = $render['html'] ?? '';
            if ($html === '') {
                throw new Exception('Пустой HTML после рендера (возможно, блокировка/капча/ошибка загрузки).');
            }

            // 3) Extract page_data from HTML
            $pageData = $extractor->extract($audit->url, $audit->final_url ?? $audit->url, $audit->http_status, $html);

            $audit->page_data = $pageData;
            $audit->save();

            // 4) LLM audit
            $t1 = microtime(true);
            $result = $llm->analyze($pageData);
            $llmMs = (int) round((microtime(true) - $t1) * 1000);

            $audit->llm_ms = $llmMs;
            $audit->result = $result;
            $audit->status = 'done';
            $audit->save();
        } catch (Exception $e) {
            $audit->status = 'failed';
            $audit->error = $e->getMessage();
            $audit->save();
        }
    }
}
