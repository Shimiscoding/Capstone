@extends('layouts.admin-dashboard')

@section('title', 'Cashier dashboard')
@section('activePage', 'cashier')
@section('workspaceClass', 'workspace-full-width cashier-workspace')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/cashier-dashboard.css') }}?v={{ filemtime(public_path('css/cashier-dashboard.css')) }}">
@endpush

@section('content')
    <header class="page-head users-page-head">
        <div>
            <p class="eyebrow">Cashier dashboard</p>
            <h1>Ticket overview</h1>
            <p class="page-description">Review issued tickets, assessed fines, and recorded payment status.</p>
        </div>
        <div class="user-count">{{ number_format($tickets->total()) }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}</div>
    </header>

    <div class="table-card">
        <div class="table-scroll" tabindex="0" role="region" aria-label="Issued tickets. Scroll horizontally to see all columns.">
            <table class="users-table cashier-tickets-table">
                <caption class="sr-only">Issued tickets, newest first. Dates and times are in Philippine time.</caption>
                <thead>
                    <tr>
                        <th scope="col">Ticket</th>
                        <th scope="col">Motorist</th>
                        <th scope="col">Plate number</th>
                        <th scope="col">Violation</th>
                        <th scope="col">Assessed fine</th>
                        <th scope="col">Status</th>
                        <th scope="col">Issued (PHT)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <th scope="row" class="cashier-ticket-id">#{{ str_pad((string) $ticket->id, 7, '0', STR_PAD_LEFT) }}</th>
                            <td>{{ $ticket->full_name ?: 'Not recorded' }}</td>
                            <td>{{ $ticket->plate_number ?: 'Not recorded' }}</td>
                            <td>{{ $ticket->violation_type ?: 'Not recorded' }}</td>
                            <td class="cashier-fine">&#8369;{{ number_format((float) $ticket->fine_amount, 2) }}</td>
                            <td><span class="status-badge {{ $ticket->status === 'paid' ? '' : 'status-pending' }}"><i aria-hidden="true"></i>{{ filled($ticket->status) ? ucfirst($ticket->status) : 'Not recorded' }}</span></td>
                            <td>
                                @if ($ticket->created_at)
                                    <time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}<small>{{ $ticket->created_at->copy()->timezone('Asia/Manila')->format('h:i A') }}</small></time>
                                @else
                                    Not recorded
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state"><strong>No tickets available</strong><span>Issued tickets will appear here once they are recorded.</span></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <footer class="table-footer">
            <span>Showing {{ $tickets->firstItem() ?? 0 }}&ndash;{{ $tickets->lastItem() ?? 0 }} of {{ number_format($tickets->total()) }}</span>
            <nav class="pagination-actions" aria-label="Ticket pages">
                @if ($tickets->onFirstPage())
                    <span class="page-button is-disabled" aria-disabled="true">Previous</span>
                @else
                    <a class="page-button" href="{{ $tickets->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                @if ($tickets->hasMorePages())
                    <a class="page-button" href="{{ $tickets->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="page-button is-disabled" aria-disabled="true">Next</span>
                @endif
            </nav>
        </footer>
    </div>
@endsection
