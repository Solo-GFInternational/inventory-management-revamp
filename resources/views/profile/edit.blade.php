@extends('layouts.app')
@section('title', 'Your profile')

@section('content')
    <x-page-header title="Your profile"
                   description="Your name, email and password. Your role, and deactivating your account, are managed by an administrator." />

    <div class="max-w-3xl space-y-8">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
    </div>
@endsection
