<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\Beximco;
use App\Models\Medicine;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Notification;
use App\Models\InventoryDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SubscriptionController extends Controller
{
  private $user;
  public function __construct(Request $request) {
    $this->user = $request->auth;
  }

  public function subscription(Request $request) {
    $user = $request->auth;

    $status = false;
    $msg = false;
    $data = $request->all();
    $coupon = DB::table('subscriptions')
    ->where('coupon_type', $data['coupon_type'])
    ->where('coupon_code', $data['coupon_code'])
    ->first();
    if($coupon) {
      if($coupon->status == 'USED') {
        $msg = 'Already used this coupon.';
      }else{
        $status = true;
        DB::table('subscriptions')->where('id',$coupon->id)->update(['status'=>'USED', 'apply_date'=>date('Y-m-d H:i:s')]);
        $branch = DB::table('pharmacy_branches')->where('id', $user->pharmacy_branch_id)->first();
        if($branch) {
          if($coupon->coupon_type == '1MONTH') {
            $subscription_period = $branch->subscription_period + 30;
          } else if($coupon->coupon_type == '3MONTH') {
            $subscription_period = $branch->subscription_period + 30 * 3;
          } else if($coupon->coupon_type == '6MONTH') {
            $subscription_period = $branch->subscription_period + 30 * 6;
          } else if($coupon->coupon_type == '1YEAR') {
            $subscription_period = $branch->subscription_period + 360;
          }
          DB::table('pharmacy_branches')->where('id', $user->pharmacy_branch_id)->update(['subscription_period'=>$subscription_period]);
        }
      }
    }else{
      $msg = 'Invalid coupon.';
    }
    return response()->json(['status'=>$status, 'message'=>$msg]);
  }

  public function subscriptionPlan(Request $request) {
    $user = $request->auth;
    $subscription = DB::table('pharmacy_branches')->where('id', $user->pharmacy_branch_id)->first();
    if($subscription) {
      $data['subscription_period'] = $subscription->subscription_period;
      $data['subscription_count'] = $subscription->subscription_count;

      return response()->json(['status'=>true, 'data'=>$data]);
    }
    return response()->json(['status'=>false, 'message'=> 'Subscription Plan not found!']);
  }

  public function subscriptionCount(Request $request) {
    $user = $request->auth;
    $subscription = DB::table('pharmacy_branches')->where('id', $user->pharmacy_branch_id)->first();
    if($subscription) {
      DB::table('pharmacy_branches')->where('id', $user->pharmacy_branch_id)->update(['subscription_count'=>$request->count]);
      return response()->json(['status'=>true]);
    }
    return response()->json(['status'=>false, 'message'=> 'Subscription Plan not found!']);
  }

  public function subscriptionCoupon() {
    $coupon = array();
    for($i=1; $i<=12; $i++) {
      $coupon[] = $this->_randomString(16);
    }
    foreach ($coupon as $key => $value) {
      $input = array(
        'pharmacy_id' => $pharmacy_id ?? 0,
        'pharmacy_branch_id' => $pharmacy_branch_id ?? 0,
        'coupon_code' => $value,
        'coupon_type' => '1MONTH',
      );
      DB::table('subscriptions')->insert($input);
    }
    return response()->json($coupon);
  }

  public function getSubscriptions() {
    $coupons = DB::table('subscriptions')->get();
    $data['coupons'] = $coupons;
    $subscription = DB::table('pharmacy_branches')->where('id', $this->user->pharmacy_branch_id)->first();
    $data['subscription_period'] = $subscription->subscription_period;
    $data['subscription_count'] = $subscription->subscription_count;

    return response()->json(['status'=>false, 'data'=> $data]);
  }

}
