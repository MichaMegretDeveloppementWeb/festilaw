@extends('layouts.web')

@section('title', __('Page not found · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/errors/index.css')
@endpush

@section('content')
    <x-web.error-page
        code="404"
        :title="__('Page not found')"
        :message="__('The page you are looking for does not exist or may have moved.')" />
@endsection
