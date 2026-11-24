<?php

namespace App\Livewire;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AdminOrderAlerts extends Component
{
    public function render(): View
    {
        abort_unless(Gate::allows('viewAny', Order::class), 403);
        $pending = Order::where('status', 'pending')->count();
        $cancellations = Order::whereHas('requests', fn ($query) => $query->where('type', 'cancellation')->where('status', 'pending'))->count();

        $corrections = Order::whereHas('requests', fn ($query) => $query->where('type', 'address')->where('status', 'pending'))->count();
        $failedMessages = Order::whereHas('events', fn ($query) => $query->whereNull('sent_at')->whereNotNull('last_error')->where('recipient', '!=', ''))->count();

        return view('livewire.admin-order-alerts', compact('pending', 'cancellations', 'corrections', 'failedMessages'));
    }
}
