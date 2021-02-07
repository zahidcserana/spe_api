<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockBalance extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function stockBalanceItems()
    {
        return $this->hasMany(StockBalanceItem::class);
    }

    public function openStockItems($user, $date)
    {
        $this->pharmacy_branch_id = $user->pharmacy_branch_id;
        $this->date_open = $date;
        $this->save();

        $products = new Product();
        $inventories = $products
            ->select(DB::raw('SUM(quantity) as quantity, medicine_id as product_id'))
            ->groupBy('medicine_id')
            ->get();

        foreach ($inventories as $inventory) {
            $stockBalanceItem = new StockBalanceItem();
            $stockBalanceItem->stock_balance_id = $this->id;
            $stockBalanceItem->product_id = $inventory->product_id;
            $stockBalanceItem->quantity_open = $inventory->quantity;
            $stockBalanceItem->save();
        }
    }

    public function closeStockItems()
    {
        foreach ($this->stockBalanceItems as $stockBalanceItem) {
            $stockQty = Product::where('medicine_id', $stockBalanceItem->product_id)->sum('quantity');

            $stockBalanceItem->quantity_close = $stockQty;

            $stockBalanceItem->update();
        }
    }
}
