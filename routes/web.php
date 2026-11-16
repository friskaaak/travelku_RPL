<?php

use App\Http\Controllers\HotelController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\TrainController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DiscountController as AdminDiscountController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentSimulationController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\EmailDeliveryController;


Route::get('/', [HomeController::class, 'index'])->name('home');

// ---------- Tamu (belum login) ----------
Route::middleware('guest')->group(function () {
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->name('login.store');
    Route::get('/masuk/lanjut', [LoginController::class, 'intend'])->name('login.intend');

    Route::get('/lupa-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    // Nama 'password.reset' wajib, dipakai oleh email reset bawaan Laravel.
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---------- SEMENTARA: diganti pada fase berikutnya ----------
$soon = fn (string $title, string $phase) => fn () => view('pages.soon', compact('title', 'phase'));
   Route::get('/verifikasi/{code}', [TicketController::class, 'verify'])
       ->where('code', '[A-Za-z0-9]{40}')->middleware('throttle:30,1')->name('tickets.verify');

// ---------- SEMENTARA: diganti pada fase berikutnya ----------
Route::get('/hotel', [HotelController::class, 'index'])->name('hotels.index');
Route::post('/webhook/pembayaran/{provider}', [PaymentWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('webhooks.payment');
Route::get('/hotel/{hotel:slug}', [HotelController::class, 'show'])->name('hotels.show');
Route::get('/pesawat', [FlightController::class, 'index'])->name('flights.index');
Route::get('/pesawat/jadwal/{schedule}', [FlightController::class, 'show'])->name('flights.show');
Route::get('/kereta', [TrainController::class, 'index'])->name('trains.index');
Route::get('/kereta/jadwal/{schedule}', [TrainController::class, 'show'])->name('trains.show');
Route::get('/promo', [CampaignController::class, 'index'])->name('campaigns.index');
Route::post('/promo/cek', [PromoController::class, 'check'])->middleware('throttle:20,1')->name('promo.check');
Route::get('/promo/{campaign:slug}', [CampaignController::class, 'show'])->name('campaigns.show');

Route::middleware('auth')->group(function () {
    Route::get('/pesanan-saya', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/pesanan/{booking:booking_code}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('/pesanan/{booking:booking_code}/tiket/pdf', [BookingController::class, 'ticketsPdf'])->middleware('throttle:20,1')->name('bookings.tickets.pdf');

    Route::get('/struk/{receipt:receipt_number}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/struk/{receipt:receipt_number}/pdf', [ReceiptController::class, 'pdf'])->middleware('throttle:20,1')->name('receipts.pdf');

    Route::get('/tiket/{ticket:ticket_number}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tiket/{ticket:ticket_number}/pdf', [TicketController::class, 'pdf'])->middleware('throttle:20,1')->name('tickets.pdf');
    Route::post('/email/{delivery}/kirim-ulang', [EmailDeliveryController::class, 'resend'])->middleware('throttle:10,1')->name('emails.resend');
    Route::post('/hotel/{hotel:slug}/hold', [HoldController::class, 'storeHotel'])->middleware('throttle:10,1')->name('hotels.hold');
    Route::post('/pesawat/jadwal/{schedule}/hold', [HoldController::class, 'storeFlight'])->middleware('throttle:10,1')->name('flights.hold');
    Route::post('/kereta/jadwal/{schedule}/hold', [HoldController::class, 'storeTrain'])->middleware('throttle:10,1')->name('trains.hold');

    Route::get('/hold/{hold:hold_code}', [HoldController::class, 'show'])->name('holds.show');
    Route::get('/hold/{hold:hold_code}/status', [HoldController::class, 'status'])->middleware('throttle:60,1')->name('holds.status');
    Route::post('/hold/{hold:hold_code}/lepas', [HoldController::class, 'release'])->name('holds.release');
    Route::get('/hold/{hold:hold_code}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/hold/{hold:hold_code}/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('/pembayaran/{payment:payment_reference}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/pembayaran/{payment:payment_reference}/status', [PaymentController::class, 'status'])->middleware('throttle:60,1')->name('payments.status');
    Route::post('/pembayaran/{payment:payment_reference}/ganti', [PaymentController::class, 'change'])->middleware('throttle:10,1')->name('payments.change');
    Route::post('/pembayaran/{payment:payment_reference}/batal', [PaymentController::class, 'cancel'])->name('payments.cancel');
    Route::post('/pembayaran/{payment:payment_reference}/simulasi', [PaymentSimulationController::class, 'store'])->middleware('throttle:20,1')->name('payments.simulate');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/audit', [AdminDashboardController::class, 'audit'])->name('admin.audit');

    // ---- Diskon ----
    Route::get('/diskon', [AdminDiscountController::class, 'index'])->name('admin.discounts.index');
    Route::get('/diskon/buat', [AdminDiscountController::class, 'create'])->name('admin.discounts.create');
    Route::post('/diskon', [AdminDiscountController::class, 'store'])->name('admin.discounts.store');
    Route::get('/diskon/{discount}/ubah', [AdminDiscountController::class, 'edit'])->name('admin.discounts.edit');
    Route::put('/diskon/{discount}', [AdminDiscountController::class, 'update'])->name('admin.discounts.update');
    Route::post('/diskon/{discount}/aktif', [AdminDiscountController::class, 'toggle'])->name('admin.discounts.toggle');
    Route::delete('/diskon/{discount}', [AdminDiscountController::class, 'destroy'])->name('admin.discounts.destroy');

    // ---- Kampanye ----
    Route::get('/kampanye', [AdminCampaignController::class, 'index'])->name('admin.campaigns.index');
    Route::get('/kampanye/buat', [AdminCampaignController::class, 'create'])->name('admin.campaigns.create');
    Route::post('/kampanye', [AdminCampaignController::class, 'store'])->name('admin.campaigns.store');
    Route::get('/kampanye/{campaign}/ubah', [AdminCampaignController::class, 'edit'])->name('admin.campaigns.edit');
    Route::put('/kampanye/{campaign}', [AdminCampaignController::class, 'update'])->name('admin.campaigns.update');
    Route::post('/kampanye/{campaign}/terbit', [AdminCampaignController::class, 'toggle'])->name('admin.campaigns.toggle');
    Route::delete('/kampanye/{campaign}', [AdminCampaignController::class, 'destroy'])->name('admin.campaigns.destroy');
});