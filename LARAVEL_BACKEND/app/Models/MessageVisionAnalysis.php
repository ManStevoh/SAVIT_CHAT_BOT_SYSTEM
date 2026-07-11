<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageVisionAnalysis extends Model
{
    protected $fillable = [
        'company_id', 'chat_id', 'message_id', 'analysis_type',
        'labels', 'product_matches', 'warranty_detected',
        'confidence', 'raw_response', 'model_used',
    ];

    protected $casts = [
        'labels' => 'array',
        'product_matches' => 'array',
        'warranty_detected' => 'boolean',
        'confidence' => 'float',
        'raw_response' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function toPromptBlock(): string
    {
        $parts = ['[Customer sent an image — vision analysis]'];
        $raw = $this->raw_response ?? [];
        if (! empty($raw['scene_summary'])) {
            $parts[] = 'Scene: '.$raw['scene_summary'];
        }
        if (! empty($this->product_matches)) {
            $names = array_column($this->product_matches, 'name');
            $parts[] = 'Possible catalog matches: '.implode(', ', $names);
        }
        if ($this->warranty_detected) {
            $parts[] = 'Warranty/receipt document detected.';
            if (! empty($raw['warranty_details'])) {
                $parts[] = 'Details: '.$raw['warranty_details'];
            }
        }
        if (! empty($raw['damage_visible'])) {
            $parts[] = 'Visible product damage or defect noted in image.';
        }

        return implode("\n", $parts);
    }
}
