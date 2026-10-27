@extends('layouts.admin-dashboard')

@section('title', 'Access denied')
@section('workspaceClass', 'workspace-full-width')

@section('content')
    <header class="page-head">
        <div>
            <p class="eyebrow">403 / Access denied</p>
            <h1>Ticket overview access only</h1>
            <p class="page-description">Your cashier account can view the ticket overview. This page is unavailable for your role.</p>
        </div>
    </header>
    <a class="page-button" href="{{ route('cashier.dashboard') }}">Back to ticket overview</a>
@endsection
