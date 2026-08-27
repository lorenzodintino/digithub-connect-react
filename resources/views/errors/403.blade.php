@extends('errors.layout')

@section('code', '403')
@section('title', __('This page is not for your account'))

@section('body')
    @auth
        <p>{{ __('The page exists, but your role does not have access to it.') }}</p>

        @if (auth()->user()->isAdmin())
            <p>{{ __('The portal back-office belongs to managers and operators. As the platform administrator you manage portals from the admin area instead.') }}</p>
        @endif
    @else
        <p>{{ __('You need to sign in with an account that has access to this page.') }}</p>
    @endauth
@endsection

@section('actions')
    @auth
        <a class="button primary" href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</a>

        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button class="button" type="submit">{{ __('Sign in as someone else') }}</button>
        </form>
    @else
        <a class="button primary" href="{{ route('login') }}">{{ __('Log in') }}</a>
    @endauth
@endsection
