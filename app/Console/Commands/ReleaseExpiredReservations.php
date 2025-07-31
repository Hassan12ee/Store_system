<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReservedQuantity;
use App\Models\Product;
use Carbon\Carbon;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'reservations:release';
    protected $description = 'Release expired product reservations and restore product quantities.';

    public function handle()
    {
        $now = Carbon::now();

        $expired = ReservedQuantity::where('expires_at', '<', $now)->get();

        foreach ($expired as $reservation) {
            $product = $reservation->product;
            if ($product) {
                $product->increment('quantity', $reservation->quantity);
            }

            $reservation->delete();
        }

        $this->info('Expired reservations released successfully.');
    }
}
    
