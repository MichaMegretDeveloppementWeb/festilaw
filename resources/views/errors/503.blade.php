@extends('layouts.web')

@section('title', __('We will be right back · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/errors/index.css')
@endpush

@section('content')
    <x-web.error-page
        code="503"
        :title="__('We will be right back')"
        :message="__('Festilaw is briefly down for maintenance. Please check back shortly.')" />
@endsection
