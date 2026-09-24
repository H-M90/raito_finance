<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $deviceProducts = [];
        foreach ([
            'pts' => ['code'=>'PTS','name'=>'جهاز PTS','unit'=>'device'],
            'sensor' => ['code'=>'SENSOR','name'=>'حساس خزان','unit'=>'sensor'],
        ] as $type => $meta) {
            $id = DB::table('products')->where('code', $meta['code'])->value('id');
            if (! $id) {
                $id = DB::table('products')->insertGetId([
                    'code'=>$meta['code'],'name'=>$meta['name'],'type'=>$type,'unit'=>$meta['unit'],
                    'default_sale_price'=>0,'billing_cycle'=>'one_time','default_maintenance_rate'=>0,
                    'is_active'=>1,'created_at'=>$now,'updated_at'=>$now,
                ]);
            } else {
                DB::table('products')->where('id',$id)->update(['type'=>$type,'unit'=>$meta['unit'],'updated_at'=>$now]);
            }
            $deviceProducts[$type] = $id;
        }

        $this->backfill('contracts','contract_items','contract_id',$deviceProducts,$now);
        $this->backfill('sales_quotations','sales_quotation_items','sales_quotation_id',$deviceProducts,$now);
        $this->backfill('contract_addendums','contract_addendum_items','contract_addendum_id',$deviceProducts,$now);
    }

    private function backfill(string $parentTable, string $itemTable, string $foreignKey, array $deviceProducts, $now): void
    {
        DB::table($parentTable)->orderBy('id')->chunkById(200, function ($parents) use ($itemTable,$foreignKey,$deviceProducts,$now) {
            foreach ($parents as $parent) {
                foreach (['pts'=>'pts_count','sensor'=>'sensor_count'] as $type=>$column) {
                    $quantity = (int) ($parent->{$column} ?? 0);
                    if ($quantity <= 0) continue;
                    $productId = $deviceProducts[$type];
                    if (DB::table($itemTable)->where($foreignKey,$parent->id)->where('product_id',$productId)->exists()) continue;
                    $sort = (int) DB::table($itemTable)->where($foreignKey,$parent->id)->max('sort_order');
                    DB::table($itemTable)->insert([
                        $foreignKey=>$parent->id,'product_id'=>$productId,'quantity'=>$quantity,
                        'requested_users'=>0,'included_users'=>0,'promotional_free_users'=>0,'billable_users'=>0,'total_licensed_users'=>0,
                        'unit_price'=>0,'user_unit_price'=>0,'user_total'=>0,'discount_value'=>0,'promotional_discount_value'=>0,
                        'line_subtotal'=>0,'line_net'=>0,'maintenance_rate'=>0,'maintenance_annual'=>0,
                        'notes'=>'تم التحويل تلقائيًا من كميات الأجهزة القديمة في رأس المستند','sort_order'=>$sort+1,
                        'created_at'=>$now,'updated_at'=>$now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Data migration: device line items are intentionally preserved on rollback.
    }
};
