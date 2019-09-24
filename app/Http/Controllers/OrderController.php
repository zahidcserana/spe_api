<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\CartItem;
use App\Models\Medicine;
use App\Models\Inventory;
use App\Models\DamageItem;
use App\Models\ConsumerGood;
use App\Models\MedicineCompany;
use App\Models\InventoryDetail;
use App\Models\Order;
use App\Models\OrderItem;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use App\Exports\PurchaseExport;
use Illuminate\Support\Facades\App;
use Validator;
use DB;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
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

    public function create(Request $request)
    {
        $data = $request->all();

        $this->validate($request, [
            'token' => 'required',
        ]);
        $orderModel = new Order();
        $order = $orderModel->makeOrder($data);

        return response()->json($order);
    }

    public function deleteItem(Request $request)
    {
        $data = $request->all();
        $cartItemModel = new OrderItem();
        $result = $cartItemModel->deleteItem($data);

        return response()->json($result);
    }

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
    public function latestPurchase(Request $request)
    {

        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['orders.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
        $query = Order::where($where);
        $orders = $query
            ->orderBy('orders.id', 'desc')
            ->limit(5)
            ->get();
        $orderData = array();
        foreach ($orders as $order) {
            $aData = array();
            $aData['token'] = $order->token;
            $aData['order_id'] = $order->id;
            $aData['invoice'] = $order->invoice;
            $aData['date'] = date("Y-m-d H:i:s", strtotime($order->created_at));

            $company = MedicineCompany::findOrFail($order->company_id);
            $aData['company'] = $company->company_name;

            $orderData[] = $aData;
        }

        return response()->json($orderData);
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
    }

    public function view($orderToken)
    {
        $order = Order::where('token', $orderToken)->first();
        $orderModel = new Order();
        return response()->json($orderModel->getOrderDetails($order->id));
    }
    public function details($orderId)
    {
        $orderModel = new Order();
        return response()->json($orderModel->getOrderDetails($orderId));
    }

    public function index()
    {
        $orders = Order::orderBy('id', 'desc')->get();
        $data = array();
        foreach ($orders as $order) {
            $aData = array();
            $aData['company_invoice'] = $order->company_invoice;
            $aData['created_at'] = date("Y-m-d H:i:s", strtotime($order->created_at));
            $pharmacy_branch = $order->PharmacyBranch;
            $aData['pharmacy_branch'] = ['id' => $pharmacy_branch['id'], 'name' => $pharmacy_branch['branch_name']];
            $data[] = $aData;
        }

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $updateQuery = $request->all();
        $updateQuery['updated_at'] = date('Y-m-d H:i:s');
        $orderStatus = Order::where('token', $request->token)->first()->status;
        //        if ($orderStatus == 'ACCEPTED') {
        //            return response()->json(['success' => false, 'status' => $orderStatus]);
        //        }

        if (Order::where('token', $request->token)->update($updateQuery)) {
            return response()->json(['success' => true, 'status' => Order::where('token', $request->token)->first()->status]);
        }
        return response()->json(['success' => false, 'status' => $orderStatus]);
    }

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

    public function manualPurchaseList(Request $request)
    {
        $query = $request->query();

        $pageNo = $request->query('page_no') ?? 1;
        $limit = $request->query('limit') ?? 1000;
        $offset = (($pageNo - 1) * $limit);
        $where = array();
        $user = $request->auth;
        $where = array_merge(array(['orders.pharmacy_branch_id', $user->pharmacy_branch_id]), $where);
        $where = array_merge(array(['orders.is_manual', true]), $where);
        $where = array_merge(array(['orders.status', 'ACCEPTED']), $where);

        if (!empty($query['company_invoice'])) {
            $where = array_merge(array(['orders.company_invoice', 'LIKE', '%' . $query['company_invoice'] . '%']), $where);
        }
        if (!empty($query['batch_no'])) {
            $where = array_merge(array(['order_items.batch_no', 'LIKE', '%' . $query['batch_no'] . '%']), $where);
        }
        if (!empty($query['exp_type'])) {
            $where = $this->_getExpCondition($where, $query['exp_type']);
        }

        $query = Order::where($where)
            ->join('order_items', 'orders.id', '=', 'order_items.order_id');

        $total = $query->count();
        $orders = $query
            ->orderBy('orders.id', 'desc')
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

            $aData['company_invoice'] = $item->company_invoice;
            $aData['is_sync'] = $item->is_sync;

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
            $aData['status'] = $item->status;

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

    public function purchaseSave(Request $request){

        $details = $request->details;
        $items = $request->items;

        $user = $request->auth;

        $orderAdd = new Order();

        $orderAdd->company_id = $details['company'];
        $orderAdd->company_invoice = $details['invoice'];
        $orderAdd->purchase_date = date('Y-m-d');
        $orderAdd->total_amount = $details['total'];
        $orderAdd->tax = $details['vat'];
        $orderAdd->discount = $details['discount'];
        $orderAdd->sub_total = $details['net_amount'];
        $orderAdd->total_payble_amount = $details['net_amount'];
        $orderAdd->total_advance_amount = $details['advance'];
        $orderAdd->total_due_amount = $details['due'];
        $orderAdd->status = "ACCEPTED";
        $orderAdd->created_by = $user->id;
        $orderAdd->pharmacy_branch_id = $user->pharmacy_branch_id;
        $orderAdd->save();

        $OrderId = $orderAdd->id;

        $orderAdd->_createOrderInvoice($OrderId, $user->pharmacy_branch_id);
        
        foreach($items as $item):
            
            $medicine_id = $item['medicine_id'];
            $medicine = Medicine::where('id', $medicine_id)->get();
            if(sizeof($medicine)){
                $company_id = $medicine[0]->company_id;
            }

            $itemSave = new OrderItem();
            $itemSave->medicine_id = $item['medicine_id'];
            $itemSave->company_id = $company_id;
            $itemSave->quantity = $item['quantity'];
            $itemSave->order_id = $OrderId;
            $itemSave->exp_date = $item['exp_date'];
            $itemSave->batch_no = $item['batch_no'];
            $itemSave->unit_price = $item['box_mrp']/$item['piece_per_box'];
            $itemSave->sub_total = $item['amount'];
            $itemSave->mrp = $item['box_mrp'];
            $itemSave->trade_price = $item['box_trade_price'];
            $itemSave->total = $item['amount'];
            $itemSave->pieces_per_box = $item['piece_per_box'];
            $itemSave->save();

            

        endforeach;

        return response()->json(array(
            'data' => "Purchase Successfull!",
            'details' => $details['due']
        ));
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

    public function orderFilterList(Request $request)
    {
        $query = $request->query();

        $filter = $request->query('filter');
        $decode_filter = json_decode($filter, true);

        $where = array();

        $mr_id = $decode_filter['mr_id'];
        $company = $decode_filter['company'];
        $invoice = $decode_filter['invoice'];
        if ($company) {
            $company_details = MedicineCompany::where('company_name', $company)->get();
            if (sizeof($company_details)) {
                $where = array_merge(array(['orders.company_id', $company_details[0]->id]), $where);
            }
        } else {
            $company_id = 0;
        }

        if ($invoice) {
            $where = array_merge(array(['orders.invoice', $invoice]), $where);
        }

        if ($mr_id) {
            $where = array_merge(array(['orders.mr_id', $mr_id]), $where);
        }

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
            ->get();

        return response()->json(array(
            'total' => $total,
            'data' => $orders,
        ));
    }

    public function getItemList(Request $request)
    {
        $order_id = $request->query('order');

        if ($order_id) {
            $orderItems = OrderItem::select('order_items.id as item_id', 'order_items.medicine_id', 'medicines.brand_name as medicine_name', 'order_items.company_id', 'order_items.quantity',
                'order_items.exp_date', 'order_items.mfg_date', 'order_items.batch_no', 'order_items.unit_price', 'order_items.discount',
                'order_items.total', 'order_items.tax as vat', 'order_items.pieces_per_strip', 'order_items.strip_per_box', 'order_items.free_qty',
                'order_items.receive_qty', 'order_items.mrp', 'order_items.trade_price', 'order_items.is_received')
                ->leftjoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
                ->where('order_id', $order_id)->get();

            if (sizeof($orderItems)) {
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
                'orders.tax as vat',
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

    public function receiveDamageItem(Request $request)
    {
        $items = $request->items;

        foreach ($items as $item):
            $company_id = 0;

            $company = MedicineCompany::where('company_name', 'like', $item['company'])->get();
            if(sizeof($company)){
                $company_id = $company[0]->id;
            }

            $medicine = Medicine::where('brand_name', 'like', $item['medicine'])
            ->when($company_id, function ($query, $company_id) {
                return $query->where('company_id', $company_id);
            })
            ->get();

            if(sizeof($medicine) && sizeof($company)){
                $medicine_id =  $medicine[0]->id;
                $checkIfExist = DamageItem::where('medicine_id', $medicine_id)->where('company_id', $company_id)->where('quantity', $item['quantity'])->get();
                if(!sizeof($checkIfExist)){

                    $inventoryDetails = InventoryDetail::where('medicine_id', $medicine_id)->where('batch_no', $item['batch_no'])->where('company_id', $company_id)->get();
                    if (sizeof($inventoryDetails)) {

                        $entryDamage = new DamageItem();
                        $entryDamage->company_id = $company_id;
                        $entryDamage->medicine_id = $medicine_id;
                        $entryDamage->batch_no = $item['batch_no'];
                        $entryDamage->unit = $item['unit_type'];
                        $entryDamage->quantity = $item['quantity'];
                        $entryDamage->remarks = $item['remarks'];
                        $entryDamage->save();

                        $inventoryinfo = InventoryDetail::find($inventoryDetails[0]->id);
                        if($inventoryinfo->quantity > $item['quantity']){
                            $rest_qty = $inventoryinfo->quantity - $item['quantity'];
                            $inventoryinfo->quantity = $rest_qty;
                        }
                        $inventoryinfo->save();

                        $inventory = Inventory::where('medicine_id', $inventoryinfo->medicine_id)->get();
                        if (sizeof($inventory)) {
                            $inventory = Inventory::find($inventory[0]->id);
                            $inventory->quantity = $inventory->quantity - $item['quantity'];
                            $inventory->save();
                        }
                    }

                }
            }
        endforeach;

        return response()->json(array(
            'data' => 'Successfull!',
            'status' => 'Successfull!'
        ));
    }

    public function damagesList()
    {
        $DamageItemList = DamageItem::select('damage_items.id', 'damage_items.quantity', 'damage_items.batch_no', 'damage_items.unit', 'damage_items.remarks', 'medicine_companies.company_name', 'medicines.brand_name as medicine_name')
            ->leftjoin('medicines', 'medicines.id', '=', 'damage_items.medicine_id')
            ->leftjoin('medicine_companies', 'damage_items.company_id', '=', 'medicine_companies.id')
            ->get();

        return response()->json(array(
            'data' => $DamageItemList,
            'status' => 'Successful',
            'message' => 'Damage Item List'
        ));
    }

    public function orderUpdate(Request $request)
    {
        $order_id           = $request->order_id;
        $company_invoice    = $request->company_invoice;
        $total_advance      = $request->total_advance ? $request->total_advance : 0;
        $total_amount       = $request->total_amount ? $request->total_amount : 0;
        $total_discount     = $request->total_discount ? $request->total_discount : 0;
        $total_due          = $request->total_due ? $request->total_due : 0;
        $total_qty          = $request->total_qty ? $request->total_qty : 0;
        $total_vat          = $request->total_vat ? $request->total_vat : 0;

        $orders = Order::find($order_id);
        $orders->company_invoice        = $company_invoice;
        $orders->total_advance_amount   = $total_advance;
        $orders->total_amount           = $total_amount;
        $orders->discount               = $total_discount;
        $orders->tax                    = $total_vat;
        $orders->quantity               = $total_qty;
        $orders->total_due_amount       = $total_due;
        $orders->sub_total              = $total_amount - $total_due;
        $orders->save();

        return response()->json(array(
            'data' => $orders,
            'status' => 'Successful',
            'message' => 'Order Information'
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

            $orderItem->free_qty = $request->free_qty ? $request->free_qty : 0;
            if ($request->mfg_date) {
                $orderItem->mfg_date = date("Y-m-d", strtotime($request->mfg_date));
            }

            $orderItem->mrp = $request->mrp ? $request->mrp : 0;
            $orderItem->pieces_per_strip = $request->pieces_per_strip ? $request->pieces_per_strip : 0;
            $orderItem->receive_qty = $request->receive_qty;
            $orderItem->strip_per_box = $request->strip_per_box ? $request->strip_per_box : 0;
            $orderItem->total = $request->receive_qty * $request->trade_price;
            $orderItem->trade_price = $request->trade_price;
            $orderItem->unit_price = $request->trade_price ? $request->trade_price : 0;
            $orderItem->tax = $request->vat ? $request->vat : 0;
            $orderItem->is_received = 1;

            $orderItem->save();
            $orderItem->medicine_name = $request->medicine_name;

            $orderId = $orderItem->order_id;
            $OrderDetailsForSave = Order::where('id', $orderId)->first();

            $inventoryDetails = InventoryDetail::where('medicine_id', $request->medicine_id)->where('batch_no', $request->batch_no)->get();
            if (sizeof($inventoryDetails)) {
                $inventoryinfo = InventoryDetail::find($inventoryDetails[0]->id);
                $qty_in_box = $inventoryinfo->quantity + $request->receive_qty;
                $inventoryinfo->quantity = $qty_in_box;
                $inventoryinfo->mrp = $request->mrp ? $request->mrp : 0;
                $inventoryinfo->batch_no = $request->batch_no;
                if ($request->exp_date) {
                    $inventoryinfo->exp_date = date("Y-m-d", strtotime($request->exp_date));
                }
                $inventoryinfo->pieces_per_strip = $request->pieces_per_strip ? $request->pieces_per_strip : 0;
                $inventoryinfo->strip_per_box = $request->strip_per_box ? $request->strip_per_box : 0;
                $inventoryinfo->save();

                $OrderDetails = Order::find($orderId);
                $OrderDetails->status = "IN-PROGRESS";
                $OrderDetails->save();
            } else {
                $OrderDetails = Order::find($orderId);
                $OrderDetails->status = "IN-PROGRESS";
                $OrderDetails->save();

                $newInventory = new InventoryDetail();
                $newInventory->medicine_id = $request->medicine_id;
                $qty_in_box = $request->receive_qty;
                $newInventory->quantity = $qty_in_box;
                $newInventory->mrp = $request->mrp ? $request->mrp : 0;
                $newInventory->batch_no = $request->batch_no;
                $newInventory->exp_date = date("Y-m-d", strtotime($request->exp_date));
                $newInventory->pieces_per_strip = $request->pieces_per_strip ? $request->pieces_per_strip : 0;
                $newInventory->strip_per_box = $request->strip_per_box ? $request->strip_per_box : 0;
                $newInventory->company_id = $OrderDetailsForSave->company_id;
                $newInventory->pharmacy_id = $OrderDetailsForSave->pharmacy_branch_id;
                $newInventory->save();
            }

            $inventory = Inventory::where('medicine_id', $request->medicine_id)->get();
            if (sizeof($inventory)) {
                $inventory = Inventory::find($inventory[0]->id);
                $qty_in_box = $inventory->quantity + $request->receive_qty;
                $inventory->quantity = $qty_in_box;
                $inventory->save();
            } else {
                $inventoryUpdate = new Inventory();
                $inventoryUpdate->medicine_id = $request->medicine_id;
                $inventoryUpdate->company_id = $OrderDetailsForSave->company_id;
                $qty_in_box = $request->receive_qty;
                $inventoryUpdate->quantity = $qty_in_box;
                $inventoryUpdate->pharmacy_branch_id = $OrderDetails->pharmacy_branch_id;
                $inventoryUpdate->save();
            }

            $OrderDetailsCheck = OrderItem::where('order_id', $orderId)->where('is_received', 0)->get();

            if(!sizeof($OrderDetailsCheck)){
                $UpdateOrderDetails = Order::find($orderId);
                $UpdateOrderDetails->status = "ACCEPTED";
                $UpdateOrderDetails->save();
            }

            return response()->json(array(
                'data' => $OrderDetailsCheck,
                'status' => 'Successful',
                'message' => 'Full Received'
            ));
        }

        return response()->json(array(
            'data' => 'No Item found',
            'status' => 'Unsuccessfull',
            'message' => 'Please, select order id!'
        ));
    }

    public function fullReceive(Request $request)
    {
        $orderInfo = $request->order;
        $itemList = $request->items;

        $total_payble_amount = $orderInfo['total_amount'] - ($orderInfo['total_discount'] ? $orderInfo['total_discount'] : 0);

        $OrderDetails = Order::find($orderInfo['order_id']);
        $OrderDetails->quantity = $orderInfo['total_qty'];
        $OrderDetails->sub_total = $orderInfo['total_amount'];
        $OrderDetails->company_invoice = $orderInfo['company_invoice'];
        $OrderDetails->tax = $orderInfo['total_vat'] ? $orderInfo['total_vat'] : 0;
        $OrderDetails->discount = $orderInfo['total_discount'] ? $orderInfo['total_discount'] : 0;
        $OrderDetails->total_amount = $orderInfo['total_amount'] ? $orderInfo['total_amount'] : 0;
        $OrderDetails->total_payble_amount = $total_payble_amount;
        $OrderDetails->total_advance_amount = $orderInfo['total_advance'] ? $orderInfo['total_advance'] : 0;
        $OrderDetails->total_due_amount = $orderInfo['total_due'];
        $OrderDetails->status = "ACCEPTED";
        $OrderDetails->save();

        foreach ($itemList as $item):
            $orderItem = OrderItem::find($item['item_id']);
            if (!$orderItem->is_received) {
                $orderItem->quantity = $item['quantity'];
                $orderItem->batch_no = $item['batch_no'];

                if ($item['exp_date']) {
                    $orderItem->exp_date = date("Y-m-d", strtotime($item['exp_date']));
                }

                $orderItem->free_qty = $item['free_qty'] ? $item['free_qty']: 0;
                $orderItem->mrp = $item['mrp'] ? $item['mrp']: 0;
                $orderItem->pieces_per_strip = $item['pieces_per_strip'] ? $item['pieces_per_strip']: 0;
                $orderItem->receive_qty = $item['receive_qty'];
                $orderItem->strip_per_box = $item['strip_per_box'] ? $item['strip_per_box']: 0;
                $orderItem->total = $item['trade_price'] * $item['receive_qty'];
                $orderItem->trade_price = $item['trade_price'];
                $orderItem->unit_price = $item['trade_price'];
                $orderItem->tax = $item['vat'] ? $item['vat']: 0;
                $orderItem->is_received = 1;
                $orderItem->save();

                $inventoryDetails = InventoryDetail::where('medicine_id', $item['medicine_id'])->where('batch_no', $item['batch_no'])->get();
                if (sizeof($inventoryDetails)) {
                    $inventoryinfo = InventoryDetail::find($inventoryDetails[0]->id);
                    $qty_in_box = $inventoryinfo->quantity + $item['receive_qty'];
                    $inventoryinfo->quantity = $qty_in_box;
                    $inventoryinfo->mrp = $item['mrp'] ? $item['mrp']: 0;
                    $inventoryinfo->batch_no = $item['batch_no'];
                    if ($item['exp_date']) {
                        $inventoryinfo->exp_date = date("Y-m-d", strtotime($item['exp_date']));
                    }
                    $inventoryinfo->pieces_per_strip = $item['pieces_per_strip'] ? $item['pieces_per_strip']: 0;
                    $inventoryinfo->strip_per_box = $item['strip_per_box'] ? $item['strip_per_box']: 0;
                    $inventoryinfo->save();
                } else {
                    $newInventory = new InventoryDetail();
                    $newInventory->medicine_id = $item['medicine_id'];
                    $qty_in_box = $item['receive_qty'];
                    $newInventory->quantity = $qty_in_box;
                    $newInventory->mrp = $item['mrp'] ? $item['mrp']: 0;
                    $newInventory->batch_no = $item['batch_no'];
                    $newInventory->exp_date = date("Y-m-d", strtotime($item['exp_date']));
                    $newInventory->pieces_per_strip = $item['pieces_per_strip'] ? $item['pieces_per_strip']: 0;
                    $newInventory->strip_per_box = $item['strip_per_box'] ? $item['strip_per_box']: 0;
                    $newInventory->company_id = $OrderDetails->company_id;
                    $newInventory->pharmacy_id = $OrderDetails->pharmacy_branch_id;
                    $newInventory->save();
                }

                $inventory = Inventory::where('medicine_id', $item['medicine_id'])->get();
                if (sizeof($inventory)) {
                    $inventory = Inventory::find($inventory[0]->id);
                    $qty_in_box = $inventory->quantity + $item['receive_qty'];
                    $inventory->quantity = $qty_in_box;
                    $inventory->save();
                } else {
                    $inventoryUpdate = new Inventory();
                    $inventoryUpdate->medicine_id = $item['medicine_id'];
                    $inventoryUpdate->company_id = $OrderDetails->company_id;
                    $qty_in_box = $item['receive_qty'];
                    $inventoryUpdate->quantity = $qty_in_box;
                    $inventoryUpdate->pharmacy_branch_id = $OrderDetails->pharmacy_branch_id;
                    $inventoryUpdate->save();
                }
            }

        endforeach;

        return response()->json(array(
            'data' => 'Order Item received',
            'status' => 'Successful',
            'message' => 'Full Received'
        ));
    }

    public function inventoryList(Request $request)
    {
        $inventory = Inventory::select('inventories.id', 'inventories.quantity', 'inventories.medicine_id', 'inventories.pharmacy_branch_id', 'medicines.brand_name', 'medicines.strength', 'medicine_companies.company_name')
            ->orderBy('medicines.brand_name', 'ASC')
            ->leftjoin('medicines', 'medicines.id', '=', 'inventories.medicine_id')
            ->leftjoin('medicine_companies', 'medicines.company_id', '=', 'medicine_companies.id')
            ->get();

        return response()->json(array(
            'data' => $inventory,
            'status' => 'Successful',
            'message' => 'Inventory List'
        ));
    }

    public function inventoryFilter(Request $request)
    {

        $filter = $request->query('filter');
        $decode_filter = json_decode($filter, true);
        $company = $decode_filter['company'];

        $company_details = MedicineCompany::where('company_name', $company)->get();
        if (sizeof($company_details)) {
            $company_id = $company_details[0]->id ? $company_details[0]->id : 0;
            $inventory = Inventory::select('inventories.id', 'inventories.quantity', 'inventories.medicine_id', 'inventories.pharmacy_branch_id', 'medicines.brand_name', 'medicine_companies.company_name')
                ->when($company_id, function ($query, $company_id) {
                    return $query->where('inventories.company_id', $company_id);
                })
                ->orderBy('medicines.brand_name', 'ASC')
                ->leftjoin('medicines', 'medicines.id', '=', 'inventories.medicine_id')
                ->leftjoin('medicine_companies', 'medicines.company_id', '=', 'medicine_companies.id')
                ->get();
        }

        return response()->json(array(
            'data' => $inventory,
            'status' => 'Successful',
            'message' => 'Inventory List filtered'
        ));
    }

    public function purchaseFilter(Request $request)
    {
        $filter = $request->query('filter');
        $decode_filter = json_decode($filter, true);

        $company =  $decode_filter['company'];
        $start_date =  $decode_filter['start_date'];
        $end_date =  $decode_filter['end_date'];
        $invoice =  $decode_filter['invoice'] ? $decode_filter['invoice'] : 0;
        $company_id = 0;

        $company_details = MedicineCompany::where('company_name', $company)->get();

        if(sizeof($company_details))
        {
            $company_id = $company_details[0]->id ? $company_details[0]->id : 0;
        }

        if($start_date && $end_date){
            $orders = Order::select('orders.id', 'orders.invoice', 'orders.purchase_date', 'orders.status', 'orders.discount', 'orders.total_amount', 'orders.total_advance_amount', 'orders.total_due_amount', 'medicine_companies.company_name')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('orders.company_id', $company_id);
            })
            ->when($invoice, function ($query, $invoice) {
                return $query->where('orders.invoice', $invoice);
            })
            ->whereBetween('orders.purchase_date', [$start_date, $end_date])
            ->where('orders.status', 'ACCEPTED')
            ->leftjoin('medicine_companies', 'medicine_companies.id', '=', 'orders.company_id')
            ->get();
        }
        else{
            $orders = Order::select('orders.id', 'orders.invoice', 'orders.purchase_date', 'orders.status', 'orders.discount', 'orders.total_amount', 'orders.total_advance_amount', 'orders.total_due_amount', 'medicine_companies.company_name')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('orders.company_id', $company_id);
            })
            ->when($invoice, function ($query, $invoice) {
                return $query->where('orders.invoice', $invoice);
            })
            ->where('orders.status', 'ACCEPTED')
            ->leftjoin('medicine_companies', 'medicine_companies.id', '=', 'orders.company_id')
            ->get();
        }

        $data = [];

        foreach($orders as $order):
            $order_id = $order->id;
            $itemList = [];

            $orderItems = OrderItem::select('medicines.brand_name', 'medicines.strength', 'order_items.receive_qty', 'order_items.batch_no', 'order_items.exp_date')
            ->where('order_items.order_id', $order_id)
            ->where('order_items.is_received', 1)
            ->leftjoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->get();

            foreach($orderItems as $item):
                $item_name = $item->brand_name . ' ' .$item->strength;
                $itemList[] = array('medicine' => $item_name , 'receive_qty' => $item->receive_qty, 'batch_no' => $item->batch_no, 'exp_date' => $item->exp_date);
            endforeach;

            $data[] = array('invoice' => $order->invoice, 'purchase_date' => $order->purchase_date, 'discount' => $order->discount, 'total_amount' => $order->total_amount, 'total_advance_amount' => $order->total_advance_amount, 'total_due_amount' => $order->total_due_amount, 'company_name' => $order->company_name, 'items' => $itemList);
        endforeach;

        return response()->json(array(
            'data' => $data,
            'status' => 'Successful',
            'message' => 'Inventory Purchase ietm list'
        ));
    }

    public function purchaseReportToExcels(Request $request)
    {
        //Excel Data
        $filter = $request->query('filter');
        $decode_filter = json_decode($filter, true);

        $company =  $decode_filter['company'];
        $start_date =  $decode_filter['start_date'];
        $end_date =  $decode_filter['end_date'];
        $invoice =  $decode_filter['invoice'] ? $decode_filter['invoice'] : 0;
        $company_id = 0;

        $company_details = MedicineCompany::where('company_name', $company)->get();

        if(sizeof($company_details))
        {
            $company_id = $company_details[0]->id ? $company_details[0]->id : 0;
        }

        if($start_date && $end_date){
            $orders = Order::select('orders.id', 'orders.invoice', 'orders.purchase_date', 'orders.status', 'orders.discount', 'orders.total_amount', 'orders.total_advance_amount', 'orders.total_due_amount', 'medicine_companies.company_name')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('orders.company_id', $company_id);
            })
            ->when($invoice, function ($query, $invoice) {
                return $query->where('orders.invoice', $invoice);
            })
            ->whereBetween('orders.purchase_date', [$start_date, $end_date])
            ->where('orders.status', 'ACCEPTED')
            ->leftjoin('medicine_companies', 'medicine_companies.id', '=', 'orders.company_id')
            ->get();
        }
        else{
            $orders = Order::select('orders.id', 'orders.invoice', 'orders.purchase_date', 'orders.status', 'orders.discount', 'orders.total_amount', 'orders.total_advance_amount', 'orders.total_due_amount', 'medicine_companies.company_name')
            ->when($company_id, function ($query, $company_id) {
                return $query->where('orders.company_id', $company_id);
            })
            ->when($invoice, function ($query, $invoice) {
                return $query->where('orders.invoice', $invoice);
            })
            ->where('orders.status', 'ACCEPTED')
            ->leftjoin('medicine_companies', 'medicine_companies.id', '=', 'orders.company_id')
            ->get();
        }

        $data = [];

        foreach($orders as $order):
            $order_id = $order->id;
            $itemList = [];

            $orderItems = OrderItem::select('medicines.brand_name', 'medicines.strength', 'order_items.receive_qty', 'order_items.batch_no', 'order_items.exp_date')
            ->where('order_items.order_id', $order_id)
            ->where('order_items.is_received', 1)
            ->leftjoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->get();

            foreach($orderItems as $item):
                $item_name = $item->brand_name . ' ' .$item->strength;
                $itemList[] = array('medicine' => $item_name , 'receive_qty' => $item->receive_qty, 'batch_no' => $item->batch_no, 'exp_date' => $item->exp_date);
            endforeach;

            $data[] = array('invoice' => $order->invoice, 'purchase_date' => $order->purchase_date, 'discount' => $order->discount, 'total_amount' => $order->total_amount, 'total_advance_amount' => $order->total_advance_amount, 'total_due_amount' => $order->total_due_amount, 'company_name' => $order->company_name, 'items' => $itemList);
        endforeach;

        //Export
        return Excel::download(new PurchaseExport($data), 'Purchase.xlsx');

    }


    public function saleFilter(Request $request)
    {
        $user = $request->auth;
        $pharmacy_branch_id = $user->pharmacy_branch_id;

        $filter = $request->query('filter');
        $decode_filter = json_decode($filter, true);

        $invoice =  $decode_filter['invoice'] ? $decode_filter['invoice'] : 0;
        $start_date =  $decode_filter['start_date'];
        $end_date =  $decode_filter['end_date'];

        if($start_date && $end_date)
        {
            $sales = Sale::select('sales.id', 'sales.invoice', 'sales.sale_date', 'sales.customer_name', 'sales.customer_mobile', 'sales.discount', 'sales.total_payble_amount')
            ->when($invoice, function ($query, $invoice) {
                return $query->where('sales.invoice', $invoice);
            })
            ->whereBetween('sales.sale_date', [$start_date, $end_date])
            ->where('sales.pharmacy_branch_id', $pharmacy_branch_id)
            ->get();
        }
        else{
            $sales = Sale::select('sales.id', 'sales.invoice', 'sales.sale_date', 'sales.customer_name', 'sales.customer_mobile', 'sales.discount', 'sales.total_payble_amount')
            ->when($invoice, function ($query, $invoice) {
                return $query->where('sales.invoice', $invoice);
            })
            ->where('sales.pharmacy_branch_id', $pharmacy_branch_id)
            ->get();
        }

        $data = [];

        foreach($sales as $individual_sale):
            $sale_id = $individual_sale->id;
            $itemList = [];

            $saleItems = SaleItem::select('medicines.brand_name', 'medicines.strength', 'sale_items.quantity', 'sale_items.batch_no', 'sale_items.unit_price', 'sale_items.sub_total', 'sale_items.unit_type')
            ->where('sale_items.sale_id', $sale_id)
            ->leftjoin('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
            ->get();

            foreach($saleItems as $item):
                $item_name = $item->brand_name . ' ' .$item->strength;
                $itemList[] = array('medicine' => $item_name , 'quantity' => $item->quantity, 'batch_no' => $item->batch_no, 'unit_price' => $item->unit_price, 'unit_type' => $item->unit_type);
            endforeach;

            $data[] = array('invoice' => $individual_sale->invoice, 'sale_date' => $individual_sale->sale_date, 'discount' => $individual_sale->discount, 'customer_name' => $individual_sale->customer_name, 'customer_mobile' => $individual_sale->customer_mobile, 'total_payble_amount' => $individual_sale->total_payble_amount, 'items' => $itemList);
        endforeach;

        return response()->json(array(
            'data' => $data,
            'status' => 'Successful',
            'message' => 'Sale items list'
        ));
    }

    public function salesReport(Request $request){

        $user = $request->auth;
        $pharmacy_branch_id = $user->pharmacy_branch_id;

        $data = [];
        $sales = Sale::select('sales.id', 'sales.invoice', 'sales.sale_date', 'sales.customer_name', 'sales.customer_mobile', 'sales.discount', 'sales.total_payble_amount')
        ->where('sales.pharmacy_branch_id', $pharmacy_branch_id)
        ->get();

        foreach($sales as $individual_sale):
            $sale_id = $individual_sale->id;
            $itemList = [];

            $saleItems = SaleItem::select('medicines.brand_name', 'medicines.strength', 'sale_items.quantity', 'sale_items.batch_no', 'sale_items.unit_price', 'sale_items.sub_total', 'sale_items.unit_type')
            ->where('sale_items.sale_id', $sale_id)
            ->leftjoin('medicines', 'medicines.id', '=', 'sale_items.medicine_id')
            ->get();

            foreach($saleItems as $item):
                $item_name = $item->brand_name . ' ' .$item->strength;
                $itemList[] = array('medicine' => $item_name , 'quantity' => $item->quantity, 'batch_no' => $item->batch_no, 'unit_price' => $item->unit_price, 'unit_type' => $item->unit_type);
            endforeach;

            $data[] = array('invoice' => $individual_sale->invoice, 'sale_date' => $individual_sale->sale_date, 'discount' => $individual_sale->discount, 'customer_name' => $individual_sale->customer_name, 'customer_mobile' => $individual_sale->customer_mobile, 'total_payble_amount' => $individual_sale->total_payble_amount, 'items' => $itemList);
        endforeach;

        return response()->json(array(
            'data' => $data,
            'status' => 'Successful',
            'message' => 'Sale items list'
        ));
    }

    public function purchaseReport(Request $request)
    {
        $data = [];
        $orders = Order::select('orders.id', 'orders.invoice', 'orders.purchase_date', 'orders.status', 'orders.discount', 'orders.total_amount', 'orders.total_advance_amount', 'orders.total_due_amount', 'medicine_companies.company_name')
        ->where('orders.status', 'ACCEPTED')
        ->leftjoin('medicine_companies', 'medicine_companies.id', '=', 'orders.company_id')
        ->get();

        foreach($orders as $order):
            $order_id = $order->id;
            $itemList = [];

            $orderItems = OrderItem::select('medicines.brand_name', 'medicines.strength', 'order_items.receive_qty', 'order_items.batch_no', 'order_items.exp_date')
            ->where('order_items.order_id', $order_id)
            ->where('order_items.is_received', 1)
            ->leftjoin('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->get();

            foreach($orderItems as $item):
                $item_name = $item->brand_name . ' ' .$item->strength;
                $itemList[] = array('medicine' => $item_name , 'receive_qty' => $item->receive_qty, 'batch_no' => $item->batch_no, 'exp_date' => $item->exp_date);
            endforeach;

            $data[] = array('invoice' => $order->invoice, 'purchase_date' => $order->purchase_date, 'discount' => $order->discount, 'total_amount' => $order->total_amount, 'total_advance_amount' => $order->total_advance_amount, 'total_due_amount' => $order->total_due_amount, 'company_name' => $order->company_name, 'items' => $itemList);
        endforeach;

        return response()->json(array(
            'data' => $data,
            'status' => 'Successful',
            'message' => 'Inventory Purchase items list'
        ));
    }

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

    public function insertconsumerproducts()
    {
        $ConsumerGood = ConsumerGood::all();

        foreach($ConsumerGood as $item):
            $company_name = $item->company_name;
            $product_name = $item->product_name;
            $volume = $item->volume;
            $price = $item->price;
            $medicine_type_id = 125;

            $company = MedicineCompany::where('company_name', 'like', $company_name)->get();

            if(sizeof($company)){
                $company_id = $company[0]->id;

                $addCP = new Medicine();
                $addCP->company_id = $company_id;
                $addCP->brand_name = $product_name;
                $addCP->strength = $volume;
                $addCP->medicine_type_id = $medicine_type_id;
                $addCP->pcs_per_box = 0;
                $addCP->pcs_per_strip = 0;
                $addCP->price_per_box = 0;
                $addCP->price_per_strip = 0;
                $addCP->price_per_pcs = $price;
                $addCP->save();
            }

        endforeach;
        echo "Done";

    }

}
