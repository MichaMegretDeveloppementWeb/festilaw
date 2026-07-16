@extends('layouts.web')

@section('title', __('Session expired · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/errors/index.css')
@endpush

@section('content')
    <x-web.error-page
        code="419"
        :title="__('Session expired')"
        :message="__('Your session timed out for security. Please go back and try again.')" />
@endsection
