<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Comment;
use App\Models\Reply;
use Session;
use Stripe;

class HomeController extends Controller
{
    public function index()
    {
        $product = Product::paginate(9);
        $comment = Comment::orderBy('id','desc')->get();
        $reply = Reply::all();

        return view('home.userpage', compact('product','comment','reply'));
    }

    public function redirect()
    {
        $usertype = Auth::user()->usertype;

        if($usertype == '1')
        {
            $total_product = Product::all()->count();
            $total_order = Order::all()->count();
            $total_user = Order::all()->count();
            $order = Order::all();
            $total_delivered = Order::where('delivery_status','=','delivered')->get()->count();
            $total_processing = Order::where('delivery_status','=','processing')->get()->count();
            $total_revenue = 0;

            foreach($order as $order)
            {
                $total_revenue = $total_revenue + $order->price;
            }

            $view['total_product'] = $total_product;
            $view['total_order'] = $total_order;
            $view['total_user'] = $total_user;
            $view['order'] = $order;
            $view['total_delivered'] = $total_delivered;
            $view['total_processing'] = $total_processing;
            $view['total_revenue'] = $total_revenue;

            return view('admin.home')->with($view);
        }
        else
        {
            $product = Product::paginate(9);
            $comment = Comment::orderBy('id','desc')->get();
            $reply = Reply::all();
            return view('home.userpage', compact('product','comment','reply'));
        }
    }

    public function product_details($id)
    {
        $product = Product::find($id);
        return view('home.product_details', compact('product'));
    }

    public function add_cart(Request $request, $id)
    {
        if(Auth::id())
        {
            $user = Auth::user();
            $userid = $user->id;

            $product = Product::find($id);
            $product_exist_id = Cart::where('product_id','=',$id)
                                ->where('user_id','=',$userid)->get('id')->first();

            if($product_exist_id)
            {
                $cart = Cart::find($product_exist_id)->first();
                $quantity = $cart->quantity;
                $cart->quantity = $quantity + $request->quantity;
                if($product->discount_price)
                {
                    $cart->price = $product->discount_price * $cart->quantity;
                }
                else
                {
                    $cart->price = $product->price * $cart->quantity;
                }
                $cart->save();
                return redirect()->back()->with('message','Product Added Successfully');
            }
            else
            {
                $cart = new Cart;
                $cart->name = $user->name;
                $cart->email = $user->email;
                $cart->phone = $user->phone;
                $cart->address = $user->address;
                $cart->user_id = $user->id;
                $cart->product_title = $product->title;

                if($product->discount_price)
                {
                    $cart->price = $product->discount_price * $request->quantity;
                }
                else
                {
                    $cart->price = $product->price * $request->quantity;
                }
                $cart->image = $product->image;
                $cart->product_id = $product->id;
                $cart->quantity = $request->quantity;

                $cart->save();
                return redirect()->back()->with('message','Product Added Successfully');
            }
        }
        else
        {
            return redirect('login');
        }
    }

    public function show_cart()
    {
        if(!Auth::id()) return redirect('login');

        $id = Auth::user()->id;
        $cart = Cart::where('user_id', '=', $id)->get();
        return view('home.showcart', compact('cart'));
    }

    public function remove_cart($id)
    {
        $cart = Cart::find($id);
        $cart->delete();

        return redirect()->back();
    }

    public function cash_order()
    {
        $user = Auth::user();
        $userid = $user->id;

        $data = Cart::where('user_id','=',$userid)->get();

        foreach($data as $data)
        {
            $order = new Order;
            $order->name = $data->name;
            $order->email = $data->email;
            $order->phone = $data->phone;
            $order->address = $data->address;
            $order->user_id = $data->user_id;
            $order->product_title = $data->product_title;
            $order->price = $data->price;
            $order->quantity = $data->quantity;
            $order->image = $data->image;
            $order->product_id = $data->product_id;

            $order->payment_status = 'cash on delivery';
            $order->delivery_status = 'processing';
            $order->save();

            $cart_id = $data->id;
            $cart = Cart::find($cart_id);
            $cart->delete();
        }

        return redirect()->back()->with('message', 'We have Receive Your Order. We will Connect with you Soon');
    }

    public function stripe($totalprice)
    {
        return view('home.stripe',compact('totalprice'));
    }

    public function stripePost(Request $request, $totalprice)
    {
        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        Stripe\Charge::create ([
                "amount" => $totalprice * 100,
                "currency" => "usd",
                "source" => $request->stripeToken,
                "description" => "Test payment from itsolutionstuff.com."
        ]);

        $user = Auth::user();
        $userid = $user->id;

        $data = Cart::where('user_id','=',$userid)->get();

        foreach($data as $data)
        {
            $order = new Order;
            $order->name = $data->name;
            $order->email = $data->email;
            $order->phone = $data->phone;
            $order->address = $data->address;
            $order->user_id = $data->user_id;
            $order->product_title = $data->product_title;
            $order->price = $data->price;
            $order->quantity = $data->quantity;
            $order->image = $data->image;
            $order->product_id = $data->product_id;

            $order->payment_status = 'Paid';
            $order->delivery_status = 'processing';
            $order->save();

            $cart_id = $data->id;
            $cart = Cart::find($cart_id);
            $cart->delete();
        }

        Session::flash('success', 'Payment successful!');
        return back();
    }

    public function show_order()
    {
        if(Auth::id())
        {
            $user = Auth::user();
            $userid = $user->id;
            $order = Order::where('user_id','=',$userid)->get();
            return view('home.order',compact('order'));
        }
        else
        {
            return redirect('login');
        }
    }

    public function cancel_order($id)
    {
        $order = Order::find($id);
        $order->delivery_status = 'You Cancelled the Order';
        $order->save();

        return redirect()->back();
    }

    public function add_comment(Request $request)
    {
        if(Auth::id())
        {
            $comment = new Comment;
            $comment->name = Auth::user()->name;
            $comment->user_id = Auth::user()->id;
            $comment->comment = $request->comment;

            $comment->save();
            return redirect()->back();
        }
        else
        {
            return redirect('login');
        }
    }

    public function add_reply(Request $request)
    {
        if(Auth::id())
        {
            $reply = new Reply;
            $reply->name = Auth::user()->name;
            $reply->user_id = Auth::user()->id;
            $reply->comment_id = $request->commentId;
            $reply->reply = $request->reply;

            $reply->save();
            return redirect()->back();
        }
        else
        {
            return redirect('login');
        }
    }

    public function product_search(Request $request)
    {
        $comment = Comment::orderBy('id','desc')->get();
        $reply = Reply::all();
        $search_text = $request->search;
        $product = Product::where('title','LIKE','%'.$search_text.'%')
                    ->orWhere('category','LIKE','%'.$search_text.'%')
                    ->paginate(10);

        return view('home.all_product',compact('product','comment','reply'));
    }

    public function search_product(Request $request)
    {
        $comment = Comment::orderBy('id','desc')->get();
        $reply = Reply::all();
        $search_text = $request->search;
        $product = Product::where('title','LIKE','%'.$search_text.'%')
                    ->orWhere('category','LIKE','%'.$search_text.'%')
                    ->paginate(10);

        return view('home.all_product',compact('product','comment','reply'));
    }

    public function products()
    {
        $product = Product::paginate(9);
        $comment = Comment::orderBy('id','desc')->get();
        $reply = Reply::all();

        return view('home.all_product', compact('product','comment','reply'));
    }
}
