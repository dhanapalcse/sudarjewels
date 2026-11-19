<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can('orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() && $user->can('orders.view');
    }

    public function create(User $user): bool
    {
        return $user->isStaff() && $user->can('orders.manage');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isStaff() && $user->can('orders.manage');
    }

    public function delete(User $user, Order $order): bool
    {
        return false; // orders are never deleted; cancel instead
    }

    /** Affiliates only ever see orders assigned to them. */
    public function affiliateView(User $user, Order $order): bool
    {
        return $user->affiliate !== null && $order->affiliate_id === $user->affiliate->id;
    }

    public function affiliateUpdate(User $user, Order $order): bool
    {
        return $this->affiliateView($user, $order)
            && $order->created_by_id === $user->id
            && $order->status->isEditableQuote()
            && $order->status !== OrderStatus::Cancelled
            && $user->affiliate->isActive();
    }
}
