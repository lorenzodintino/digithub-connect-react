@extends('errors.layout')

@section('code', '500')
@section('title', __('Something went wrong on our side'))

@section('body')
    <p>{{ __('The problem has been recorded. Try again in a moment.') }}</p>
@endsection

@section('actions')
    @auth
        <a class="button primary" href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>
    @else
        <a class="button primary" href="{{ route('home') }}">{{ __('Go to the home page') }}</a>
    @endauth
@endsection
