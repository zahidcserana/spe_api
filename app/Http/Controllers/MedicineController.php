<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicineController extends Controller
{
    public function medicineWithExpiredDate(Request $request) {
      $data = $request->query();
      $pageNo = $request->query('page_no') ?? 1;
      $limit = $request->query('limit') ?? 500;
      $offset = (($pageNo - 1) * $limit);
      $where = array();
      $user = $request->auth;
      $where = array_merge(array(['orders.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
      $where = array_merge(array(['order_items.exp_date', '<>' ,'1970-01-01']), $where);

      if (!empty($data['medicine_id'])) {
          $where = array_merge(array(['order_items.medicine_id', $data['medicine_id']]), $where);
      }
      if (!empty($data['company_id'])) {
          $where = array_merge(array(['order_items.company_id', $data['company_id']]), $where);
      }
      if (!empty($data['expiry_date'])) {
          $dateRange = explode(',',$data['expiry_date']);
          // $query = Sale::where($where)->whereBetween('created_at', $dateRange);
          $where = array_merge(array([DB::raw('DATE(exp_date)'), '>=', $dateRange[0]]), $where);
          $where = array_merge(array([DB::raw('DATE(exp_date)'), '<=', $dateRange[1]]), $where);
      }
      $query = DB::table('orders')
      ->select('medicine_companies.company_name as company', 'medicines.brand_name', 'medicines.strength', 'medicine_types.name as type',
      'order_items.batch_no', 'order_items.exp_date',DB::raw('(order_items.quantity * order_items.pieces_per_box) AS qty'), DB::raw('DATE_FORMAT(order_items.created_at, "%Y-%m-%d") as purchase_date'))
      ->join('order_items', 'orders.id', '=', 'order_items.order_id')
      ->join('medicines', 'order_items.medicine_id', '=', 'medicines.id')
      ->join('medicine_types', 'medicines.medicine_type_id', '=', 'medicine_types.id')
      ->join('medicine_companies', 'order_items.company_id', '=', 'medicine_companies.id')
      ->where($where);
      $total = $query->count();
      $items = $query
          ->orderBy('order_items.exp_date', 'desc')
          ->offset($offset)
          ->limit($limit)
          ->get();

      foreach ($items as $item) {
        $item->exp_status = $this->_getExpStatus($item->exp_date);
        $item->exp_condition = $this->_getExpCondition($item->exp_date);
      }
      $data = array(
          'total' => $total,
          'data' => $items,
          'page_no' => $pageNo,
          'limit' => $limit,
      );
      return response()->json($data);
    }

    private function _getExpStatus($date)
    {
        $expDate = date("F, Y", strtotime($date));

        $today = date('Y-m-d');
        $exp1M = date('Y-m-d', strtotime("+1 months", strtotime(date('Y-m-d'))));
        $exp3M = date('Y-m-d', strtotime("+3 months", strtotime(date('Y-m-d'))));
        if ($date < $today) {
            return 'EXP';
        } else if ($date <= $exp3M) {
            return '3M';
        } else {
            return 'OK';
        }
    }
    private function _getExpCondition($date)
    {
        $expDate = date("F, Y", strtotime($date));

        $today = date('Y-m-d');
        $exp1M = date('Y-m-d', strtotime("+1 months", strtotime(date('Y-m-d'))));
        $exp3M = date('Y-m-d', strtotime("+3 months", strtotime(date('Y-m-d'))));
        if ($date < $today) {
            return 'EXPIRED';
        } else if ($date <= $exp3M) {
            return 'EXPIRED SOON';
        } else {
            return 'VALID';
        }
    }

    public function search(Request $request)
    {
        $str = $request->input('search');

        $companyData = $request->input('company') ? MedicineCompany::where('company_name', 'like', $request->input('company'))->first() : 0;

        $company_id =  $companyData ? $companyData->id : 0;

        $medicines = Medicine::where('brand_name', 'like', $str . '%')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('company_id', $company_id);
            })
            ->orderBy('brand_name','asc')
            ->get();
        $data = array();
        foreach ($medicines as $medicine) {
            $medicineStr = $medicine->brand_name . ' (' . $medicine->strength . ',' . $medicine->medicineType->name . ')';
            $data[] = ['id' => $medicine->id, 'name' => $medicineStr];
        }
        return response()->json($data);
    }

    public function searchByPharmacy(Request $request)
    {
        $str = $request->input('search');
        $openSale = true;
        $pharmacyMedicineIds = $openSale ? false : DB::table('inventories')->select('medicine_id')->distinct()->pluck('medicine_id');

        $medicines = Medicine::where('brand_name', 'like', $str . '%')
            ->when($pharmacyMedicineIds, function ($query, $pharmacyMedicineIds) {
                   return $query->whereIn('id', $pharmacyMedicineIds);
               })
            ->orderBy('brand_name','asc')
            ->get();
        $data = array();
        foreach ($medicines as $medicine) {
            $company = DB::table('medicine_companies')->where('id', $medicine->company_id)->first();
            $medicineType = $medicine->product_type == 1 ? $medicine->medicineType->name : ' CP';
            $medicineStr = $medicine->brand_name . ' (' . $medicine->strength . ',' . $medicineType . ')';
            $data[] = ['id'=>$medicine->id, 'name' => $medicineStr, 'company' => $company->company_name];
        }
        return response()->json($data);
    }

    public function searchMedicineFromInventory(Request $request)
    {
        $str = $request->input('search');

        $companyData = $request->input('company') ? MedicineCompany::where('company_name', 'like', $request->input('company'))->first() : 0;
        $company_id =  $companyData ? $companyData->id : 0;
        $pharmacyMedicineIds = DB::table('products')->select('medicine_id')->distinct()->pluck('medicine_id');

        $medicines = Medicine::where('brand_name', 'like', $str . '%')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('company_id', $company_id);
            })
            ->when($pharmacyMedicineIds, function ($query, $pharmacyMedicineIds) {
                return $query->whereIn('id', $pharmacyMedicineIds);
            })
            ->get();
        $data = array();
        foreach ($medicines as $medicine) {
            $medicineStr = $medicine->brand_name . ' (' . $medicine->strength . ',' . $medicine->medicineType->name . ')';
            $data[] = ['id' => $medicine->id, 'name' => $medicineStr];
        }
        return response()->json($data);
    }

    public function batchList(Request $request)
    {
        $batches = DB::table('products')->where('medicine_id', $request->input('medicine_id'))->pluck('batch_no');

        return response()->json($batches);
    }

    public function getAvailableQuantity(Request $request)
    {
        $product = DB::table('products')
        ->select(DB::raw('SUM(quantity) as available_quantity'))
        ->where('medicine_id', $request->input('medicine_id'))
        ->first();

        return response()->json($product);
    }

    public function searchByCompany(Request $request)
    {
        $companyId = $request->input('company');
        $medicines = Medicine::where('company_id', $companyId)
            ->limit(100)
            ->get();
        $data = array();
        foreach ($medicines as $medicine) {
            $aData = array();
            $aData['id'] = $medicine->id;
            $aData['brand_name'] = $medicine->brand_name;
            $aData['generic_name'] = $medicine->generic_name;
            $aData['strength'] = $medicine->strength;
            $aData['dar_no'] = $medicine->dar_no;
            $aData['price_per_pcs'] = $medicine->price_per_pcs;
            $aData['price_per_box'] = $medicine->price_per_box;
            $aData['price_per_strip'] = $medicine->price_per_strip;
            $aData['pcs_per_box'] = $medicine->pcs_per_box;
            $aData['pcs_per_strip'] = $medicine->pcs_per_strip;

            $aData['medicine_type'] = $medicine->medicineType->name;

            $data[] = $aData;
        }

        return response()->json($data);
    }
}
