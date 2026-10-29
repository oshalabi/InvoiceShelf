@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_item_sales_label'))
@section('footer-label', __('pdf_total_sales_label'))

{{-- Block form rather than the inline @section($name, $value) one: the inline
     form escapes what it is given, and format_money_pdf() returns markup. --}}
@section('footer-value'){!! format_money_pdf($totalAmount, $currency) !!}@endsection

@section('report-body')
    {{-- One table for every item, not one table per item: separate tables size
         their columns independently, which is why the amounts used to wander. --}}
    <div class="report-section">
        <table class="report-table">
            <thead>
                <tr>
                    <th>@lang('pdf_report_item_label')</th>
                    <th class="report-amount report-col-count">@lang('pdf_quantity_label')</th>
                    <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="report-amount">{{ $item->total_quantity }}</td>
                        <td class="report-amount">{!! format_money_pdf($item->total_amount, $currency) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="report-muted" colspan="3">@lang('pdf_report_no_records')</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
