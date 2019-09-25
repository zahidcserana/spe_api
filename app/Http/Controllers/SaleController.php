<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Medicine;
use App\Models\MedicineCompany;
use App\Models\Sale;
use App\Models\OrderItem;
use App\Models\SaleItem;
use App\Models\CartItem;
use Barryvdh\DomPDF\PDF;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Validator;

class SaleController extends Controller
{
    public function downloadPDF($orderId)
    {
        $orderModel = new Order();
        $order = $orderModel->getOrderDetails($orderId);
        $order['no'] = 1;
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadView('pdf', compact('order'))->setPaper('a4', 'portrait');

        return $pdf->download('order.pdf');
    }

    public function uploadimage(Request $request)
    {
      if ($request->hasFile('file'))
      {
            $user = $request->auth;
            $file      = $request->file('file');
            $filename  = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $picture   = $user->pharmacy_branch_id.date('YmdHis').'-'.$extension;

            $dir = 'assets/prescription_image/'. $picture;
            $file->move('assets/prescription_image', $picture);

            $im = file_get_contents($dir);
            $uploded_image_file_bytecode = base64_encode($im);

            $cartModel = new Cart();
            $checkFile = $cartModel->where('token', $request->token)->whereNotNull('file_name')->first();
            // return response()->json(["message" => $checkFile]);

            if($checkFile && file_exists('assets/prescription_image/'. $checkFile->file_name)){
              unlink('assets/prescription_image/'. $checkFile->file_name);
            }

            $cartData = $cartModel->where('token', $request->token)->update(['file' => $uploded_image_file_bytecode, 'file_name' => $picture]);

            return response()->json(['success'=>true, "file_name" => $picture]);
      } else
      {
            return response()->json(["message" => "Select image first."]);
      }
    }

    public function create(Request $request)
    {
        $data = $request->all();

        $this->validate($request, [
            'token' => 'required',
        ]);
        $orderModel = new Sale();
        $order = $orderModel->makeOrder($data);
        // if($order['success'] == true && $data['sendsms']) {
        //   $data = array(
        //     'mobile' => $order['data']['customer_mobile'],
        //     'message' => 'Thank you for your order. Your Order Invoice is '. $order['data']['invoice'] . '.'
        //   );
        //   $this->_sendMessage($data);
        // }
        return response()->json($order);
    }
    private function _sendMessage($data) {
      $curl = curl_init();
      curl_setopt_array($curl, array(
          CURLOPT_URL => "http://35.162.97.16/api/v0.0.3/send-sms-api",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30000,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => json_encode($data),
          CURLOPT_HTTPHEADER => array(
              // Set here requred headers
              "accept: */*",
              "accept-language: en-US,en;q=0.8",
              "content-type: application/json",
          ),
      ));
      $response = curl_exec($curl);
      $err = curl_error($curl);
      curl_close($curl);
    }

    public function deleteItem(Request $request)
    {
        $data = $request->all();
        $cartItemModel = new SaleItem();
        $result = $cartItemModel->deleteItem($data);

        return response()->json($result);
    }
  /*
        public function checkIsLastItem($itemId)
        {
            $item = OrderItem::find($itemId);

            $status = false;
            $order = OrderItem::where('order_id', $item->order_id)->count();

            if ($order > 1) {
                $status = true;
            }
            return response()->json(['status' => $status]);
        }

        public function manualOrder(Request $request)
        {
            $user = $request->auth;

            $data = $request->all();

            $orderModel = new Order();
            $order = $orderModel->makeManualOrder($data, $user);

            return response()->json($order);
        }

        public function manualPurchase(Request $request)
        {
            $user = $request->auth;

            $data = $request->all();

            $orderModel = new Order();
            $order = $orderModel->makeManualPurchase($data, $user);

            return response()->json($order);
        }

        public function orderItems(Request $request)
        {
            $pageNo = $request->query('page_no') ?? 1;
            $limit = $request->query('limit') ?? 1000;
            $offset = (($pageNo - 1) * $limit);
            $where = array();
            $user = $request->auth;

            $where = array_merge(array(['orders.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);

            $orderModel = new Order();
            $orders = $orderModel->getAllOrder($where, $offset, $limit);

            return response()->json($orders);
        }*/

    public function view($saleId)
    {
        $orderModel = new Sale();
        $order = DB::table('sales')->where('id', $saleId)->first();
        if(empty($order)){
          $data['order_items'] = [];
          return response()->json($data);
        }
        return response()->json($orderModel->getOrderDetails($saleId));
    }

    public function latestSale(Request $request)
    {
        $query = $request->query();

        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['sales.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
        if (!empty($query['invoice'])) {
            $where = array_merge(array(['sales.invoice', 'LIKE', '%' . $query['invoice'] . '%']), $where);
        }

        if (!empty($query['customer_mobile'])) {
            $where = array_merge(array(['sales.customer_mobile', 'LIKE', '%' . $query['customer_mobile'] . '%']), $where);
        }
        if (!empty($query['sale_date'])) {
            $date = explode('GMT', $query['sale_date']);
            $timestamp = strtotime($date[0]);
            $saleDate = date('Y-m-d', $timestamp);

            $query = Sale::where($where)->whereDate('created_at', '=', $saleDate);

        } else {
            $query = Sale::where($where);
        }

        $total = $query->count();
        $orders = $query
            ->orderBy('sales.id', 'desc')
            ->limit(5)
            ->get();
        $orderData = array();
        foreach ($orders as $order) {
            $aData = array();
            $aData['sale_id'] = $order->id;
            $aData['customer_name'] = $order->customer_name;
            $aData['customer_mobile'] = $order->customer_mobile;
            $aData['invoice'] = $order->invoice;
            $aData['total_payble_amount'] = $order->total_payble_amount;
            $aData['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));

            $orderData[] = $aData;
        }

        return response()->json($orderData);
    }

    public function saleReport(Request $request)
    {
        $data = $request->query();
        $pageNo = $request->query('page_no') ?? 1;
        $limit = $request->query('limit') ?? 500;
        $offset = (($pageNo - 1) * $limit);
        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['sales.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
        if (!empty($data['invoice'])) {
            $where = array_merge(array(['sales.invoice', 'LIKE', '%' . $data['invoice'] . '%']), $where);
        }
        if (!empty($data['customer_mobile'])) {
            $where = array_merge(array(['sales.customer_mobile', 'LIKE', '%' . $data['customer_mobile'] . '%']), $where);
        }
        if (!empty($data['sale_date'])) {
            $dateRange = explode(',',$data['sale_date']);
            // $query = Sale::where($where)->whereBetween('created_at', $dateRange);
            $where = array_merge(array([DB::raw('DATE(created_at)'), '>=', $dateRange[0]]), $where);
            $where = array_merge(array([DB::raw('DATE(created_at)'), '<=', $dateRange[1]]), $where);
        }
        $query = Sale::where($where);
        $total = $query->count();
        $orders = $query
            ->orderBy('sales.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        $orderData = array();
        foreach ($orders as $order) {
            $aData = array();
            $aData['id'] = $order->id;
            $aData['customer_name'] = $order->customer_name;
            $aData['customer_mobile'] = $order->customer_mobile;
            $aData['invoice'] = $order->invoice;
            $aData['total_payble_amount'] = $order->total_payble_amount;
            $aData['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
            $aData['image'] = $order->file_name ?? '';
            $orderData[] = $aData;
        }
        $data = array(
            'total' => $total,
            'data' => $orderData,
            'page_no' => $pageNo,
            'limit' => $limit,
        );
        return response()->json($data);
    }

    public function update(Request $request)
    {
        $user = $request->auth;
        $data = $request->all();
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $user->id;
        $itemData = SaleItem::where('id', $data['item_id'])->first();
        $saleData = Sale::where('id', $itemData->sale_id)->first();

        $changeLog = $this->_changeLog($itemData, $data);
        $medicineInfo = DB::table('products')
        ->where('medicine_id', $itemData->medicine_id)
        ->first();
        $saleItem = new SaleItem();
        $saleItem->updateInventoryQuantity($itemData->medicine_id, $itemData->quantity - $data['new_quantity'], 'add');

        $input = array(
          'quantity' => $data['new_quantity'],
          'unit_type' => $data['unit_type'] ?? 'PCS',
          'sub_total' => $itemData->unit_price * $data['new_quantity'],
          'change_log' => json_encode($changeLog),
          'updated_at' => $data['updated_at'],
          'updated_by' => $data['updated_by'],
          'return_status' => 'CHANGE'
        );
        $saleModel = new Sale();

        if ($itemData->update($input)) {
          $saleModel->updateOrder($itemData->sale_id);
          return response()->json(['success' => true, 'data' => $saleModel->getOrderDetails($itemData->sale_id)]);
        }
        return response()->json(['success' => false, 'data' => $saleModel->getOrderDetails($itemData->sale_id)]);
    }
    public function _changeLog($itemData, $data){
      $changeLog = array();
      $changeLog = json_decode($itemData->change_log ,true);
      if(empty($changeLog)) {
        $changeLog[] = array(
          'quantity' => $itemData['quantity'],
          'unit_price' => $itemData['unit_price'],
          'sub_total' => $itemData['sub_total'],
          'created_at' => $itemData['created_at']
        );
      }
      $changeLog[] = $data;
      return $changeLog;
    }
  /*
    public function statusUpdate(Request $request)
    {
        $updateQuery = $request->all();
        $updateQuery['updated_at'] = date('Y-m-d H:i:s');

        $changeStatus = OrderItem::find($request->item_id)->is_status_updated;
        if ($changeStatus) {
            return response()->json(['success' => false, 'error' => 'Status Already changed']);
        }
        unset($updateQuery['item_id']);
        $updateQuery['is_status_updated'] = true;
        if (OrderItem::find($request->item_id)->update($updateQuery)) {
            return response()->json(['success' => true, 'status' => OrderItem::find($request->item_id)->status]);
        }
        return response()->json(['success' => false, 'error' => 'Already changed']);
    }

    public function saleReport_Old(Request $request)
    {
        $query = $request->query();

        $pageNo = $request->query('page_no') ?? 1;
        $limit = $request->query('limit') ?? 1000;
        $offset = (($pageNo - 1) * $limit);
        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['sales.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);

        if (!empty($query['company_invoice'])) {
            $where = array_merge(array(['orders.company_invoice', 'LIKE', '%' . $query['company_invoice'] . '%']), $where);
        }
        if (!empty($query['batch_no'])) {
            $where = array_merge(array(['order_items.batch_no', 'LIKE', '%' . $query['batch_no'] . '%']), $where);
        }
        if (!empty($query['exp_type'])) {
            $where = $this->_getExpCondition($where, $query['exp_type']);
        }

        $query = Sale::where($where)
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id');

        $total = $query->count();
        $orders = $query
            ->orderBy('sales.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        $orderData = array();
        foreach ($orders as $item) {
            //$items = $order->items()->get();
            $aData = array();
            $aData['id'] = $item->id;
            $aData['order_id'] = $item->order_id;

            $company = MedicineCompany::findOrFail($item->company_id);
            $aData['company'] = ['id' => $company->id, 'name' => $company->company_name];

            $aData['company_invoice'] = $item->invoice;
            $aData['is_sync'] = 0;

            $medicine = Medicine::findOrFail($item->medicine_id);
            $aData['medicine'] = ['id' => $medicine->id, 'brand_name' => $medicine->brand_name];

            $aData['exp_date'] = date("M, Y", strtotime($item->exp_date));
            $aData['purchase_date'] = date("F, Y", strtotime($item->purchase_date));
            //$aData['exp_date'] = date("F, Y", strtotime($item->exp_date));
            $aData['exp_status'] = $this->_getExpStatus($item->exp_date);
            $aData['mfg_date'] = date("M, Y", strtotime($item->mfg_date));

            //$aData['mfg_date'] = $item->mfg_date;
            $aData['batch_no'] = $item->batch_no;
            $aData['quantity'] = $item->quantity;
            $aData['sub_total'] = $item->sub_total;
            $aData['unit_type'] = $item->unit_type;
            $aData['status'] = '';

            $orderData[] = $aData;
        }

        $data = array(
            'total' => $total,
            'data' => $orderData,
            'page_no' => $pageNo,
            'limit' => $limit,
        );

        return response()->json($data);
    }

    public function getOrderList(Request $request)
    {
        $query = $request->query();

        $pageNo = $request->query('page_no') ?? 1;
        $limit = $request->query('limit') ?? 100;
        $offset = (($pageNo - 1) * $limit);

        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['orders.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
        $where = array_merge(array(['orders.is_manual', true]), $where);

        $query = Order::select('orders.id as order_id',
            'orders.company_id',
            'medicine_companies.company_name',
            'orders.invoice',
            'orders.company_invoice',
            'orders.mr_id',
            'mrs.mr_full_name as mr_name',
            'orders.purchase_date',
            'orders.quantity',
            'orders.sub_total',
            'orders.tax as vat',
            'orders.discount',
            'orders.total_amount',
            'orders.total_payble_amount',
            'orders.total_advance_amount',
            'orders.total_due_amount',
            'orders.payment_type',
            'orders.status',
            'orders.created_by')->where($where)
            ->leftjoin('medicine_companies', 'orders.company_id', '=', 'medicine_companies.id')
            ->leftjoin('mrs', 'orders.mr_id', '=', 'mrs.id');

        $total = $query->count();
        $orders = $query
            ->orderBy('orders.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json(array(
            'total' => $total,
            'page_no' => $pageNo,
            'limit' => $limit,
            'data' => $orders,
        ));
    }

    public function getItemList(Request $request)
    {
        $order_id = $request->query('order');

        if ($order_id) {
            $orderItems = OrderItem::select('order_items.id as item_id', 'medicines.brand_name as medicine_name', 'order_items.quantity',
                'order_items.exp_date', 'order_items.mfg_date', 'order_items.batch_no', 'order_items.unit_price', 'order_items.discount',
                'order_items.total', 'order_items.tax', 'order_items.pieces_per_strip', 'order_items.strip_per_box', 'order_items.free_qty',
                'order_items.receive_qty', 'order_items.mrp', 'order_items.trade_price', 'order_items.is_received')
                ->leftjoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
                ->where('order_id', $order_id)->get();

            if (count($orderItems)) {
                return response()->json(array(
                    'data' => $orderItems,
                    'status' => 'Successful'
                ));
            }
            return response()->json(array(
                'data' => '',
                'status' => 'Successful',
                'message' => 'No Item found'
            ));
        }

        return response()->json(array(
            'data' => 'No Item found',
            'status' => 'Unsuccessfull',
            'message' => 'Please, select order id!'
        ));

    }

    public function getOrderDetails($orderId)
    {

        if ($orderId) {
            $OrderInfo = Order::select('orders.id as order_id',
                'orders.company_id',
                'medicine_companies.company_name',
                'orders.invoice',
                'orders.company_invoice',
                'orders.mr_id',
                'mrs.mr_full_name as mr_name',
                'orders.purchase_date',
                'orders.quantity',
                'orders.sub_total',
                'orders.tax',
                'orders.discount',
                'orders.total_amount',
                'orders.total_payble_amount',
                'orders.total_advance_amount',
                'orders.total_due_amount',
                'orders.payment_type',
                'orders.status',
                'orders.created_by')->where('orders.id', $orderId)
                ->leftjoin('medicine_companies', 'orders.company_id', '=', 'medicine_companies.id')
                ->leftjoin('mrs', 'orders.mr_id', '=', 'mrs.id')
                ->first();

            return response()->json(array(
                'data' => $OrderInfo,
                'status' => 'Successful',
            ));
        }

        return response()->json(array(
            'data' => '',
            'status' => 'Unsuccessfull!'
        ));
    }

    public function receiveItem(Request $request)
    {

        $item_id = $request->item_id;

        if ($item_id) {
            $orderItem = OrderItem::find($item_id);
            $orderItem->quantity = $request->quantity;
            $orderItem->batch_no = $request->batch_no;
            if ($request->exp_date) {
                $orderItem->exp_date = date("Y-m-d", strtotime($request->exp_date));
            }
            $orderItem->free_qty = $request->free_qty;
            if ($request->mfg_date) {
                $orderItem->mfg_date = date("Y-m-d", strtotime($request->mfg_date));
            }
            $orderItem->mrp = $request->mrp;
            $orderItem->pieces_per_strip = $request->pieces_per_strip;
            $orderItem->receive_qty = $request->receive_qty;
            $orderItem->strip_per_box = $request->strip_per_box;
            $orderItem->total = $request->total;
            $orderItem->trade_price = $request->trade_price;
            $orderItem->unit_price = $request->unit_price;
            $orderItem->tax = $request->vat;
            $orderItem->is_received = 1;

            $orderItem->save();
            $orderItem->medicine_name = $request->medicine_name;

            return response()->json(array(
                'data' => $orderItem,
                'status' => 'Successful'
            ));
        }

        return response()->json(array(
            'data' => 'No Item found',
            'status' => 'Unsuccessfull',
            'message' => 'Please, select order id!'
        ));
    }*/

    private function _getExpStatus($date)
    {
        $expDate = date("F, Y", strtotime($date));

        $today = date('Y-m-d');
        $exp1M = date('Y-m-d', strtotime("+1 months", strtotime(date('Y-m-d'))));
        $exp3M = date('Y-m-d', strtotime("+3 months", strtotime(date('Y-m-d'))));
        if ($date < $today) {
            return 'EXP';
        } else if ($date >= $today && $date <= $exp1M) {
            return '1M';
        } else if ($date > $exp1M && $date <= $exp3M) {
            return '3M';
        } else {
            return 'OK';
        }
    }

    private function _getExpCondition($where, $expTpe)
    {
        $today = date('Y-m-d');
        $exp1M = date('Y-m-d', strtotime("+1 months", strtotime(date('Y-m-d'))));
        $exp3M = date('Y-m-d', strtotime("+3 months", strtotime(date('Y-m-d'))));
        if ($expTpe == 2) {
            $where = array_merge(array(
                ['order_items.exp_date', '>', $today],
                ['order_items.exp_date', '<', $exp1M]
            ), $where);
        } else if ($expTpe == 3) {
            $where = array_merge(array(
                ['order_items.exp_date', '>', $exp1M],
                ['order_items.exp_date', '<', $exp3M]
            ), $where);
        } else if ($expTpe == 1) {
            $where = array_merge(array(
                ['order_items.exp_date', '>', $exp3M]
            ), $where);
        } else if ($expTpe == 4) {
            $where = array_merge(array(['order_items.exp_date', '<', $today]), $where);
        }
        return $where;
    }


}
