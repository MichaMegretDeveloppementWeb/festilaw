@extends('layouts.web')

@section('title', 'Access my file · Festilaw')
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/get-started/journey.css')
@endpush

@section('content')
    <section class="my-file">
        <div class="my-file__inner">
            <header class="my-file__head">
                <span class="eyebrow">Your file</span>
                <h1 class="my-file__title">Access <span class="my-file__title-em">my file</span></h1>
                <p class="my-file__intro">Enter your email and we'll send you a secure link to your Festilaw file · no account, no password.</p>
            </header>

            <div class="my-file__card">
                <livewire:web.funnel.access-file-form />
            </div>
        </div>
    </section>
@endsection
