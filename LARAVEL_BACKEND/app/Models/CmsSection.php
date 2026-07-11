<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsSection extends Model
{
    protected $fillable = [
        'cms_page_id',
        'section_key',
