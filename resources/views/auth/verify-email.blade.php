@extends('layouts.auth')
@section('title', 'Verify email')
@section('form-action', route('verification.send'))
@section('form-attributes')data-processing="Sending the verification link…" data-processing-text="Please wait, this can take a moment."@endsection
@section('heading', 'Verify your email')
@section('subheading', "Click the link we emailed you to verify your address. Didn't get it? We can send another.")

@section('content')
    <x-button type="submit" size="lg" class="w-full">Resend verification email</x-button>
@endsection

{{-- Signing out is its own form, below the card: forms can't nest. --}}
@section('after-form')
    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="text-sm font-medium text-white/80 hover:text-white hover:underline">Sign out</button>
    </form>
@endsection
