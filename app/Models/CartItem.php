<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CartItem extends Model
{
    protected $guarded = [];

    public function addItem($data)
    {
        $medicine = new Medicine();
        $medicineData = $medicine->where('id', $data['medicine_id'])->first();
        if (empty($medicineData)) {
            return false;
        }
        $medicineInfo = DB::table('products')->where('medicine_id', $data['medicine_id'])->first();
        $data['unit_type'] = $data['unit_type'] ?? 'PCS';

        $unitPrice = $this->_getMedicineUnitPrice($medicineInfo, $data['unit_type']);
        $item = array(
            'medicine_id' => $medicineData->id,
            'company_id' => $medicineData->company_id,
            'quantity' => $data['quantity'],
            'batch_no' => $medicineInfo->batch_no,
            'exp_date' => $medicineInfo->exp_date,
            'cart_id' => $data['cart_id'],
            'unit_type' => $data['unit_type'],
            'unit_price' => $unitPrice,
            'sub_total' => $unitPrice * $data['quantity'],
        );

        $cartItem = CartItem::insertGetId($item);
        if ($cartItem) {
            return true;
        }
        return false;
    }

    public function _getMedicineUnitPrice($medicineInfo, $unit_type)
    {
        $unitPrice = 0;
        if ($unit_type == 'BOX') {
            $unitPrice = $medicineInfo->mrp;
        } else if ($unit_type == 'STRIP') {
            $unitPrice = $medicineInfo->mrp / $medicineInfo->strip_per_box;
        } else if ($unit_type == 'PCS') {
            $unitPrice = $medicineInfo->mrp / ($medicineInfo->strip_per_box * $medicineInfo->pieces_per_strip);
        }
        return $unitPrice;
    }

    public function updatePrice($data, $item) {
      $updateData = array(
          'unit_price' => $data['item_price'],
          'sub_total' => $item->quantity * $data['item_price'],
          'updated_at' => date('Y-m-d H:i:s'),
      );
      $this::where('id', $item->id)->update($updateData);
      return true;
    }

    public function updateQuantity($data)
    {
        $cartItem = $this::where('id', $data['id'])->first();
        if ($cartItem) {
            if ($data['increment'] == 1) {
                $updateData = array(
                    'quantity' => $cartItem->quantity + 1,
                    'sub_total' => ($cartItem->quantity + 1) * $cartItem->unit_price,
                    'updated_at' => date('Y-m-d H:i:s'),
                );
                $this::where('id', $cartItem->id)->update($updateData);
                return true;
            } else {
                $updateData = array(
                    'quantity' => $cartItem->quantity - 1,
                    'sub_total' => ($cartItem->quantity - 1) * $cartItem->unit_price,
                    'updated_at' => date('Y-m-d H:i:s'),
                );
                $this::where('id', $cartItem->id)->update($updateData);
                return true;
            }
        }
        return false;
    }

    public function medicine()
    {
        return $this->belongsTo('App\Models\Medicine');
    }

    public function deleteItem($data)
    {
        $cartModel = new Cart();
        $cart = $cartModel::where('token', $data['token'])->first();
        $this::where('id', $data['item_id'])->delete();

        $cartModel->updateCart($cart->id);
        $cartDetails = $cartModel->getCartDetails($cart->id);

        return ['success' => true, 'data' => $cartDetails];
    }

  public function company()
  {
      return $this->belongsTo('App\Models\MedicineCompany');
  }
}
