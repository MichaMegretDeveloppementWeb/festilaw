@extends('layouts.web')

@section('title', __('Something went wrong · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/errors/index.css')
@endpush

@section('content')
    <x-web.error-page
        code="500"
        :title="__('Something went wrong')"
        :message="__('An unexpected error occurred on our end. Please try again in a moment.')" />
@endsection
