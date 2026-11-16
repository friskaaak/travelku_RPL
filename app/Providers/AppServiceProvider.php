<?php

namespace App\Providers;

use App\Services\Inventory\HoldService;
use App\Services\Payment\DemoPaymentProvider;
use App\Services\Payment\MidtransProvider;
use App\Services\Payment\PaymentProviderInterface;
use App\Support\PaymentMode;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mode demo memakai simulasi lokal. Mode production memakai penyedia resmi.
        $this->app->bind(PaymentProviderInterface::class, function () {
            if (PaymentMode::isDemo()) {
                return new DemoPaymentProvider();
            }

            return match (config('payment.provider')) {
                'midtrans' => new MidtransProvider(),
                default => throw new \RuntimeException('PAYMENT_PROVIDER tidak dikenal: '.config('payment.provider')),
            };
        });
    }

    public function boot(): void
    {
        // Tombol halaman memakai gaya Bootstrap 5.
        Paginator::useBootstrapFive();

        // Spanduk "penahanan aktif" di semua halaman untuk pengguna yang sedang masuk.
        View::composer('layouts.app', function ($view) {
            $view->with(
                'activeHold',
                auth()->check() ? app(HoldService::class)->activeFor(auth()->user()) : null
            );
        });
    }
}