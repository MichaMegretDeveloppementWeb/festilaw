@extends('layouts.web')

@section('title', 'Contact · Festilaw')
@section('meta_description', 'Get in touch with Festilaw, your GPSR Responsible Person in the EU. From entrepreneurs, for entrepreneurs.')

@push('styles')
    @vite('resources/css/web/contact/index.css')
@endpush

@section('content')
    <section class="contact">
        <div class="contact__inner">
            <div class="contact__intro">
                <span class="eyebrow contact__eyebrow">Contact</span>
                <h1 class="contact__title">Let's talk about your <span class="contact__title-em">compliance</span></h1>
                <p class="contact__lead">A dedicated duo of experts, from entrepreneurs to entrepreneurs. Tell us about your situation and we'll get back to you quickly.</p>

                <div class="contact__coords">
                    <a href="mailto:team@festilaw.com" class="contact__coord">
                        <span class="contact__coord-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <span class="contact__coord-body">
                            <span class="contact__coord-label">Email us</span>
                            <span class="contact__coord-value">team@festilaw.com</span>
                        </span>
                    </a>
                    <div class="contact__coord contact__coord--soon">
                        <span class="contact__coord-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
                        </span>
                        <span class="contact__coord-body">
                            <span class="contact__coord-label">WhatsApp</span>
                            <span class="contact__coord-value">Coming soon</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="contact__card">
                <livewire:web.contact.contact-form />
            </div>
        </div>
    </section>
@endsection
