@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_expense_report_label'))
@section('footer-label', __('pdf_total_expenses_label'))

{{-- Block form rather than the inline @section($name, $value) one: the inline
     form escapes what it is given, and format_money_pdf() returns markup. --}}
@section('footer-value'){!! format_money_pdf($totalExpense, $currency) !!}@endsection

@section('report-body')
    @forelse ($expenseGroups as $group)
        <div class="report-section">
            <p class="report-section-heading">{{ $group['name'] }}</p>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="report-col-date">@lang('pdf_expense_date_label')</th>
                        <th>@lang('pdf_expense_note_label')</th>
                        <th class="report-amount report-col-amount">@lang('pdf_expense_amount_label')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group['expenses'] as $expense)
                        <tr>
                            <td>{{ $expense->formatted_expense_date }}</td>
                            <td>{{ $expense->notes ?: '-' }}</td>
                            <td class="report-amount">{!! format_money_pdf($expense->base_amount, $currency) !!}</td>
                        </tr>
                    @endforeach
                    <tr class="report-total-row">
                        <td class="report-total" colspan="2">@lang('pdf_total')</td>
                        <td class="report-amount report-total">{!! format_money_pdf($group['total'], $currency) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <div class="report-section">
            <p class="report-section-heading">@lang('pdf_expenses_label')</p>
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="report-col-date">@lang('pdf_expense_date_label')</th>
                        <th>@lang('pdf_expense_note_label')</th>
                        <th class="report-amount report-col-amount">@lang('pdf_expense_amount_label')</th>
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
