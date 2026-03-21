<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::middleware(['auth'])->group(function () {
    Route::get('/events/{event}/scanner', \App\Livewire\TicketScanner::class)->name('scanner');
});
