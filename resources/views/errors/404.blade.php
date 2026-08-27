@extends('errors.layout')

@section('code', '404')
@section('title', __('This page does not exist'))

@section('body')
    <p>{{ __('The link may be wrong, or what it pointed at has been removed.') }}</p>
@endsection

@section('actions')
    @auth
        <a class="button primary" href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
    @else
        <a class="button primary" href="{{ route('home') }}">{{ __('Go to the home page') }}</a>
    @endauth
@endsection
