@props(['status'])
@php
    use App\Enums\Submission\SubmissionStatus;

    $tone = match ($status) {
        SubmissionStatus::Paid, SubmissionStatus::Completed => 'green',
        SubmissionStatus::AwaitingPayment, SubmissionStatus::AwaitingDocuments => 'amber',
        SubmissionStatus::Cancelled => 'red',
        SubmissionStatus::New, SubmissionStatus::InProgress => 'blue',
    };
@endphp
<span class="admin-badge admin-badge--{{ $tone }}">{{ $status->label() }}</span>
