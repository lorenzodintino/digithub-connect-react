@extends('errors.layout')

@section('code', '419')
@section('title', __('Your session expired'))

@section('body')
    <p>{{ __('The page sat open too long. Reload it and try again: nothing was lost.') }}</p>
@endsection

@section('actions')
    <a class="button primary" href="{{ url()->current() }}">{{ __('Reload the page') }}</a>
@endsection
