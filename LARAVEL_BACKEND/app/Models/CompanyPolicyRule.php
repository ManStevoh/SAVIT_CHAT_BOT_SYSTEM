<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPolicyRule extends Model
{
    protected $fillable = [
        'company_id',
        'action_type',
