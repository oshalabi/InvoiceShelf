@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_customer_sales_report'))
@section('footer-label', __('pdf_total_sales_label'))

{{-- Block form rather than the inline @section($name, $value) one: the inline
     form escapes what it is given, and format_money_pdf() returns markup. --}}
@section('footer-value'){!! format_money_pdf($totalAmount, $currency) !!}@endsection

@section('report-body')
    {{-- A section is only worth a heading and a total if the customer has
         documents in the range. The controller narrows the customer list and the
         invoices relation in two separate places, and this report reads only the
         relation, so it decides on that rather than trusting the two to agree.
         Filtering here rather than in the controller also keeps the shared view
         data identical for custom templates. --}}
    @php
        $customersWithSales = $customers->filter(fn ($customer) => $customer->invoices->isNotEmpty());
    @endphp

    @forelse ($customersWithSales as $customer)
        <div class="report-section">
            <p class="report-section-heading">{{ $customer->name }}</p>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="report-col-date">@lang('pdf_report_date_label')</th>
                        <th>@lang('pdf_report_document_label')</th>
                        <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customer->invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->formattedInvoiceDate }}</td>
                            {{-- A credit note is an invoice row of another type, and it stays in
                                 the totals because a reversal netting the sale out is correct.
                                 The tag is only so the line is not read as a sale. It reuses the
                                 document's own label rather than a report-local key: every
                                 pdf_*credit* key has to exist in the shipped locales, and that
                                 one already does. --}}
                            <td>
                                {{ $invoice->invoice_number }}
                                @if ($invoice->isCreditNote())
                                    <span class="report-muted">@lang('pdf_credit_note_label')</span>
                                @endif
                            </td>
                            <td class="report-amount">{!! format_money_pdf($invoice->base_total, $currency) !!}</td>
                        </tr>
                    @endforeach
                    <tr class="report-total-row">
                        <td class="report-total" colspan="2">@lang('pdf_total')</td>
                        <td class="report-amount report-total">{!! format_money_pdf($customer->totalAmount, $currency) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <div class="report-section">
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="report-col-date">@lang('pdf_report_date_label')</th>
                        <th>@lang('pdf_report_document_label')</th>
                        <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="report-muted" colspan="3">@lang('pdf_report_no_records')</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforelse
@endsection
