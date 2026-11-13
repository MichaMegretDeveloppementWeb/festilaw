@extends('layouts.web')

@section('title', __('Page removed · Festilaw'))
@section('robots', 'noindex, nofollow')

@push('styles')
    @vite('resources/css/web/errors/index.css')
@endpush

@section('content')
    <x-web.error-page
        code="410"
        :title="__('This page no longer exists')"
        :message="__('The article you are looking for has been removed and will not come back.')" />
@endsection
