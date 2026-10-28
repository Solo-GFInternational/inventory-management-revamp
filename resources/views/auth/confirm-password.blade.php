@extends('layouts.auth')
@section('title', 'Confirm password')
@section('form-action', route('password.confirm'))
@section('heading', 'Confirm your password')
@section('subheading', 'This is a secure area. Please confirm your password to continue.')

@section('content')
    <div>
        <label for="password" class="gf-label">Password</label>
        @include('auth.partials.password-input', ['id' => 'password', 'autocomplete' => 'current-password', 'autofocus' => true])
    </div>

    <x-button type="submit" size="lg" class="w-full">Confirm</x-button>
@endsection
