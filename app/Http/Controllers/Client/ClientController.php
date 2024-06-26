<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Order;
use App\Models\OrderDetail;
use Validator;
use Str;
use DB;

class ClientController extends Controller
{
    public function index(){

        if(!Shop::exists()){
            return redirect()->route('register');
        }

        $data = [
            'shop' => Shop::first(),
            'product' => Product::all()->sortByDesc('id')->take(8),
            'category' => Category::all()->sortByDesc('id')->take(4),
            'title' => 'Home'
        ];

        return view('client.index', $data);
    }

    public function products(){
        $data = [
            'shop' => Shop::first(),
            'product' => Product::orderBy('id', 'DESC')->paginate(16),
            'category' => Category::all()->sortByDesc('id'),
            'title' => 'Products'
        ];

        return view('client.products', $data);
    }

    public function searchProduct(Request $request){
        $validator = Validator::make($request->all(), [
            'product' => 'required'
        ]);

        if($validator->fails()){
            return redirect()->route('clientHome')->withErrors($validator)->withInput();
        }else{
            
            $search = str_replace(' ', '-', strtolower($request->product));

            $data = [
                'title' => 'Result',
                'shop' => Shop::first(),
                'product' => Product::where('title', 'LIKE', '%'.$search.'%')->orderBy('id', 'DESC')->paginate(20),
                'search' => $request->product
            ];

            return view('client.productSearch', $data);

        }
    }

    public function category(){
        $data = [
            'shop' => Shop::first(),
            'category' => Category::orderBy('id', 'DESC')->paginate(12),
            'title' => 'Products'
        ];

        return view('client.category', $data);
    }

    public function categoryProducts($category){
        $data = [
            'shop' => Shop::first(),
            'category' => Category::where('name', $category)->first(),
            'title' => 'Category - '.str_replace('-', ' ', ucwords($category))
        ];

        return view('client.categoryProducts', $data);
    }

    public function productDetail($product){

        $product = Product::where('title', $product)->first();

        if($product->category->product->count() > 1){
            $recomendationProducts = $product->category->product->take(8);
        }else{
            $recomendationProducts = Product::all()->sortByDesc('id')->take(8);
        }

        $data = [
            'shop' => Shop::first(),
            'product' => $product,
            'recomendationProducts' => $recomendationProducts,
            'title' => str_replace('-', ' ', ucwords($product->title))
        ];

        return view('client.productDetail', $data);
    }

    public function checkout(){
        $data = [
            'shop' => Shop::first(),
            'title' => 'Checkout'
        ];

        return view('client.checkout', $data);
    }

    public function checkoutSave(Request $request){
        $validator = Validator($request->all(), [
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required'
        ]);

        if($validator->fails()){
            return redirect()->route('clientCheckout')->withErrors($validator)->withInput();
        }else{
            $order_code = Str::random(3).'-'.Date('Ymd');

            if(session('cart')){
                try{
                    DB::beginTransaction();                                      

                    $total = 0;
                    foreach((array) session('cart') as $id => $details){
                        $total += $details['price'] * $details['quantity'];
    
                        $data[$id] = [
                            'order_code' => $order_code,
                            'title' => $details['title'],
                            'price' => $details['price'],
                            'quantity' => $details['quantity'],
                        ];
                    }
    
                    $order = Order::create([
                        'shop_id' => Shop::first()->id,
                        'order_code' => $order_code,
                        'name' => $request->name,
                        'phone' => $request->phone,
                        'address' => $request->address,
                        'note' => $request->note,
                        'total' => $total,
                        'status' => 0,
                        'status_payment' => 'Unpaid'
                    ]);
    
                    OrderDetail::insert($data);
    
                    session()->forget('cart');

                     // Set your Merchant Server Key
                     \Midtrans\Config::$serverKey = config('midtrans.server_key');
                     // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
                     \Midtrans\Config::$isProduction = config('midtrans.is_production');
                     // Set sanitization on (default)
                     \Midtrans\Config::$isSanitized = true;
                     // Set 3DS transaction for credit card to true
                     \Midtrans\Config::$is3ds = true;  

                    $params = array(
                        'transaction_details' => array(
                            'order_id' => $order_code,
                            'gross_amount' =>$total,
                        ),
                        'customer_details' => array(
                            'first_name' => $request->name,
                            'last_name' => null,
                            'phone' => $request->phone
                        ),
                    );

                    $snapToken = \Midtrans\Snap::getSnapToken($params);
                    DB::commit();

                    return view('client.checkout-payment', compact('snapToken', 'order'));
        
                    // return redirect()->route('clientOrderCode', $order_code);
                }catch(Exception $e){
                    DB::rollBack();
                    return redirect('/');
                }
                
            }

        }
    }

    //bypass
    public function orderPayment(Request $request){
        $name = $request->name;
        $phone = $request->phone;
        $total_price = $request->total_price;
        return view('client.order-payment',compact('name', 'phone','total_price'));
    }

    public function saveOrder(Request $request){
        try{
            $order_code = Str::random(3).'-'.Date('Ymd');
            DB::beginTransaction();                                      

            $order = Order::create([
                'shop_id' => Shop::first()->id,
                'order_code' => $order_code,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => 'Jalan Dummy',
                'note' => 'Lorem Ipsum',
                'total' => $request->total_price,
                'status' => 0,
                'status_payment' => 'Unpaid'
            ]);


                // Set your Merchant Server Key
                \Midtrans\Config::$serverKey = config('midtrans.server_key');
                // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
                \Midtrans\Config::$isProduction = config('midtrans.is_production');
                // Set sanitization on (default)
                \Midtrans\Config::$isSanitized = true;
                // Set 3DS transaction for credit card to true
                \Midtrans\Config::$is3ds = true;  

            $params = array(
                'transaction_details' => array(
                    'order_id' => $order_code,
                    'gross_amount' =>$request->total_price,
                ),
                'customer_details' => array(
                    'first_name' => $request->name,
                    'last_name' => null,
                    'phone' => $request->phone
                ),
            );

            $snapToken = \Midtrans\Snap::getSnapToken($params);
            DB::commit();

            return response()->json(['status' => 'success', 'order_code'=>$order_code, 'token' => $snapToken],200);

            // return redirect()->route('clientOrderCode', $order_code);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json(['status' => 'failed'],500);
        }   
    }
    
    public function orderSaveBypass(Request $request){
        
        try{
            $order_code = Str::random(3).'-'.Date('Ymd');
            DB::beginTransaction();                                      

            $order = Order::create([
                'shop_id' => Shop::first()->id,
                'order_code' => $order_code,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => 'Jalan Dummy',
                'note' => 'Lorem Ipsum',
                'total' => $request->total_price,
                'status' => 0,
                'status_payment' => 'Unpaid'
            ]);


                // Set your Merchant Server Key
                \Midtrans\Config::$serverKey = config('midtrans.server_key');
                // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
                \Midtrans\Config::$isProduction = config('midtrans.is_production');
                // Set sanitization on (default)
                \Midtrans\Config::$isSanitized = true;
                // Set 3DS transaction for credit card to true
                \Midtrans\Config::$is3ds = true;  

            $params = array(
                'transaction_details' => array(
                    'order_id' => $order_code,
                    'gross_amount' =>$request->total_price,
                ),
                'customer_details' => array(
                    'first_name' => $request->name,
                    'last_name' => null,
                    'phone' => $request->phone
                ),
            );

            $snapToken = \Midtrans\Snap::getSnapToken($params);
            DB::commit();

            return view('client.checkout-payment', compact('snapToken', 'order'));

            // return redirect()->route('clientOrderCode', $order_code);
        }catch(Exception $e){
            DB::rollBack();
            return redirect('/');
        }   
    }

    public function callback(Request $request){
        $serverKey = config('midtrans.server_key');
        $hashed = hash("sha512", $request->order_id.$request->status_code.$request->gross_amount.$serverKey);
        if($hashed == $request->signature_key){
            if($request->transaction_status == 'capture' || $request->transaction_status == 'settlement'){
                $order = Order::where('order_code', $request->order_id)->first();
                $order_code = $request->order_id;
                $order->update(['status_payment' => 'Paid']);
                $data = [
                    'shop' => Shop::first(),
                    'order_code' => $order_code,
                    'title' => 'Checkout'
                ];
                return view('client.success-order', $data);
            }
        }
    }

    public function successOrder($order_code){
        $data = [
            'shop' => Shop::first(),
            'order_code' => $order_code,
            'title' => 'Checkout'
        ];

        return view('client.success-order', $data);
    }
    

    public function checkOrder(){
        $data = [
            'shop' => Shop::first(),
            'title' => 'Check Order'
        ];

        return view('client.check-order', $data);
    }

    public function checkOrderStatus(Request $request){

        $order = Order::where('order_code', $request->order_code)->first();


        if($order){
            $data = [
                'shop' => Shop::first(),
                'order' => $order,
                'orderDetail' => OrderDetail::where('order_code', $request->order_code)->get(),
                'title' => 'Check Order'
            ];
            return view('client.check-order', $data);

        }

        $data = [
            'shop' => Shop::first(),
            'title' => 'Check Order'
        ];

        return view('client.check-order', $data);
    }

    public function about(){
        $data = [
            'shop' => Shop::first(),
            'title' => 'About'
        ];

        return view('client.about', $data);
    }

    public function getTransaksi()
    {

        $get_data = Order::all();
        // dd($get_data);
        $json = json_decode(json_encode($get_data));
        return response()->json(['status' => 'success', 'result' => $json]);
    }

}
