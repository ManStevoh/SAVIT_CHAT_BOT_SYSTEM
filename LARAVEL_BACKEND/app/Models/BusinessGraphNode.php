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

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_WAREHOUSE = 'warehouse';

    public const TYPE_CATEGORY = 'category';

    protected $fillable = [
        'company_id',
        'node_type',
        'ref_type',
        'ref_id',
        'label',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
