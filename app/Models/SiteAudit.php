<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAudit extends Model
{
    protected $fillable = [
        'url','final_url','status','http_status','render_ms','llm_ms','page_data','result','error'
    ];

    protected $casts = [
        'page_data' => 'array',
        'result' => 'array',
    ];
}
