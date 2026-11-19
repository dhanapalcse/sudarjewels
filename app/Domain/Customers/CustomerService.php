<?php

namespace App\Domain\Customers;

use App\Models\Customer;
use App\Support\Phone;

class CustomerService
{
    /**
     * Matches by normalised phone. An existing customer's profile is never overwritten
     * by order data entered by staff or affiliates.
     */
    public function findOrCreate(string $name, string $phone, ?string $email = null): Customer
    {
        $normalized = Phone::normalize($phone) ?? $phone;

        return Customer::firstOrCreate(['phone' => $normalized], ['name' => trim($name), 'email' => $email]);
    }
}
