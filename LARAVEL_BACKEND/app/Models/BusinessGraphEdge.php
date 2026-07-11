<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessGraphEdge extends Model
{
    protected $fillable = [
        'company_id',
        'from_node_id',
