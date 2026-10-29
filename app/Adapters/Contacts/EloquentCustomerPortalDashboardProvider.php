<?php

namespace App\Adapters\Contacts;

use App\Domains\Contacts\Contracts\CustomerPortalDashboardProvider;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;

class EloquentCustomerPortalDashboardProvider implements CustomerPortalDashboardProvider
{
    public function get(Customer $customer): array
    {
        $issuedInvoices = Invoice::whereCustomer($customer->id)
            ->where('status', '<>', 'DRAFT');

        return [
            'due_amount' => (clone $issuedInvoices)->sum('due_amount'),
            'recentInvoices' => (clone $issuedInvoices)->take(5)->latest()->get(),
            'recentEstimates' => Estimate::whereCustomer($customer->id)
                ->where('status', '<>', 'DRAFT')
                ->take(5)
                ->latest()
                ->get(),
            'invoice_count' => (clone $issuedInvoices)->where('type', Invoice::TYPE_INVOICE)->count(),
            'estimate_count' => Estimate::whereCustomer($customer->id)
                ->where('status', '<>', 'DRAFT')
                ->count(),
            'payment_count' => Payment::whereCustomer($customer->id)->count(),
        ];
    }
}
