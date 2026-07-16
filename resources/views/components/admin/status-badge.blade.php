@props(['status'])
@php
    use App\Enums\Submission\SubmissionStatus;

    $classes = match ($status) {
        SubmissionStatus::Paid, SubmissionStatus::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        SubmissionStatus::AwaitingPayment, SubmissionStatus::AwaitingDocuments => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        SubmissionStatus::Cancelled => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        SubmissionStatus::New, SubmissionStatus::InProgress => 'bg-brand-50 text-brand-700 ring-brand-600/20',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $classes }}">{{ $status->label() }}</span>
