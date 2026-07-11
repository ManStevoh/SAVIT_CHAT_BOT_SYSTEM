<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessGraphNode extends Model
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_ORDER = 'order';

    public const TYPE_CAMPAIGN = 'campaign';

