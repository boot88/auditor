<?php

namespace App\Http\Controllers;

use App\Jobs\RunSiteAuditJob;
use App\Models\SiteAudit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteAuditController extends Controller
{
    public function create()
    {
        return view('audit.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required','string','max:2048', 'regex:/^https?:\/\//i'],
        ], [
            'url.regex' => 'URL должен начинаться с http:// или https://',
        ]);

        $audit = SiteAudit::create([
            'url' => trim($data['url']),
            'status' => 'pending',
        ]);

        RunSiteAuditJob::dispatch($audit->id);

        return redirect()->route('audit.show', $audit);
    }

    public function show(SiteAudit $audit)
    {
        return view('audit.show', compact('audit'));
    }

    public function status(SiteAudit $audit)
    {
        return response()->json([
            'id' => $audit->id,
            'status' => $audit->status,
            'error' => $audit->error,
            'result' => $audit->result,
            'final_url' => $audit->final_url,
            'http_status' => $audit->http_status,
            'render_ms' => $audit->render_ms,
            'llm_ms' => $audit->llm_ms,
        ]);
    }
}
