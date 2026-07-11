<?php

namespace App\Services\Agent\Graph;

use App\Models\BusinessGraphEdge;
use App\Models\BusinessGraphNode;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRelationship;
use App\Models\WhatsAppCampaign;
use Illuminate\Support\Facades\DB;

/**
 * Business Graph v2 — traversable nodes beyond products (suppliers, warehouses, campaigns).
 */
final class BusinessGraphV2Service
{
