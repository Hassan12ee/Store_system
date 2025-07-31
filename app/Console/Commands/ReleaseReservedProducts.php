<?php

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Cart;
use App\Models\Product;

class ReleaseReservedProducts extends Command
{
    protected $signature = 'products:release-reserved';
    protected $description = 'Release products reserved in carts after 10 minutes';

    public function handle()
    {
        $expiredCarts = Cart::where('reserved_at', '<=', Carbon::now()->subMinutes(10))->get();

        foreach ($expiredCarts as $cart) {
            $product = $cart->product;
            if ($product) {
                $product->reserved_quantity -= $cart->quantity;
                $product->save();

                // reset وقت الحجز
                $cart->reserved_at = null;
                $cart->save();
            }
        }

        $this->info('Expired reservations released.');
    }
}
