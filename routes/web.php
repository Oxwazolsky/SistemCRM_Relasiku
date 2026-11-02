<?php

use App\Http\Controllers\{AdminController, AuthController, CrmController, DashboardController, InquiryWorkflowController, ContactTransferController, PublicInquiryController, WhatsappWebhookController, WhatsappHandoffController, WebsiteConsultationController, BaileysDemoController};
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware(['auth', 'crm.role:cs,owner,admin']);
Route::post('/internal/baileys-demo/message', [BaileysDemoController::class, 'receive'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('throttle:120,1')->name('baileys.demo.receive');
Route::get('/konsultasi', [WebsiteConsultationController::class, 'show'])->name('consultation.show');
Route::post('/konsultasi', [WebsiteConsultationController::class, 'submit'])->middleware('throttle:20,1')->name('consultation.submit');
Route::get('/webhooks/whatsapp', [WhatsappWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('/webhooks/whatsapp', [WhatsappWebhookController::class, 'receive'])->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)->name('whatsapp.webhook.receive');
Route::get('/inquiry', [PublicInquiryController::class, 'create'])->name('inquiry.create');
Route::post('/inquiry', [PublicInquiryController::class, 'store'])->middleware('throttle:5,1')->name('inquiry.store');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware(['auth', 'crm.role:cs,owner,admin'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    foreach (['contacts', 'inquiries', 'opportunities', 'tasks', 'orders', 'tickets'] as $type) {
        Route::get('/'.$type, [CrmController::class, 'index'])->defaults('type', $type)->name($type.'.index');
        if ($type !== 'orders') {
            Route::get('/'.$type.'/create', [CrmController::class, 'create'])->defaults('type', $type)->name($type.'.create');
            Route::post('/'.$type, [CrmController::class, 'store'])->defaults('type', $type)->name($type.'.store');
        }
        Route::get('/'.$type.'/{id}', [CrmController::class, 'show'])->defaults('type', $type)->whereNumber('id')->name($type.'.show');
        Route::get('/'.$type.'/{id}/edit', [CrmController::class, 'edit'])->defaults('type', $type)->whereNumber('id')->name($type.'.edit');
        Route::put('/'.$type.'/{id}', [CrmController::class, 'update'])->defaults('type', $type)->whereNumber('id')->name($type.'.update');
    }
    Route::get('/whatsapp/handoffs', [WhatsappHandoffController::class, 'index'])->middleware('crm.role:cs,owner')->name('whatsapp.handoffs');
    Route::get('/inquiry-attachments/{attachment}', [\App\Http\Controllers\InquiryAttachmentController::class, 'show'])->name('inquiry-attachments.show');
    Route::post('/interactions', [CrmController::class, 'store'])->defaults('type', 'interactions')->name('interactions.store');
    Route::post('/inquiries/{inquiry}/confirm-order', [InquiryWorkflowController::class, 'confirmOrder'])->name('inquiries.confirm-order');
    Route::post('/inquiries/{inquiry}/handling', [\App\Http\Controllers\HandlingController::class, 'inquiry'])->name('inquiries.handling');
    Route::post('/whatsapp/handoffs/{conversation}/handling', [\App\Http\Controllers\HandlingController::class, 'conversation'])->name('whatsapp.handling');
    Route::get('/inbox', [WhatsappHandoffController::class, 'index'])->name('inbox');
    Route::put('/whatsapp/handoffs/{conversation}/assign', [WhatsappHandoffController::class, 'assign'])->middleware('crm.role:owner,admin')->name('whatsapp.handoffs.assign');
    Route::put('/inquiries/{inquiry}/verify', [InquiryWorkflowController::class, 'verify'])->name('inquiries.verify');
    Route::put('/inquiries/{inquiry}/assign', [InquiryWorkflowController::class, 'assign'])->middleware('crm.role:owner,admin')->name('inquiries.assign');

});
Route::middleware(['auth', 'crm.role:owner'])->group(function () {
    Route::get('/reports', [DashboardController::class, 'report'])->name('reports.index');
    Route::get('/contacts/{contact}/transfer', [ContactTransferController::class, 'edit'])->name('contacts.transfer.edit');
    Route::put('/contacts/{contact}/transfer', [ContactTransferController::class, 'update'])->name('contacts.transfer.update');
});
Route::middleware(['auth', 'crm.role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/faqs', [\App\Http\Controllers\BotFaqController::class, 'index'])->name('faqs');
    Route::post('/faqs', [\App\Http\Controllers\BotFaqController::class, 'save'])->name('faqs.store');
    Route::put('/faqs/{faq}', [\App\Http\Controllers\BotFaqController::class, 'save'])->name('faqs.update');
    foreach (['users', 'services'] as $type) {
        Route::get('/'.$type, [AdminController::class, 'index'])->defaults('type', $type)->name($type.'.index');
        Route::get('/'.$type.'/create', [AdminController::class, 'form'])->defaults('type', $type)->name($type.'.create');
        Route::post('/'.$type, [AdminController::class, 'store'])->defaults('type', $type)->name($type.'.store');
        Route::get('/'.$type.'/{id}/edit', [AdminController::class, 'form'])->defaults('type', $type)->whereNumber('id')->name($type.'.edit');
        Route::put('/'.$type.'/{id}', [AdminController::class, 'update'])->defaults('type', $type)->whereNumber('id')->name($type.'.update');
    }

});
