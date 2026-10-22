@extends('layouts.app')
@section('title', 'Vehicle Expenses')
@section('content')
<div id="expenses-page" data-type="vehicle" data-form-url="{{ route('expenses.addEdit', ['type' => 'vehicle']) }}" data-data-url="{{ route('expenses.getData') }}" data-delete-url="{{ route('expenses.vehicle.destroy', ['vehicleExpense' => '__ID__']) }}">
    <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-2">Cost management</p>
            <h2 class="h2 fw-bold mb-1">Vehicle expenses</h2>
            <p class="text-secondary small mb-0">Track maintenance, fuel, and other vehicle costs.</p>
        </div>
        <x-action-button action="expenses.add" label="Add vehicle expense" />
    </header>

    <ul class="nav nav-tabs mb-3" aria-label="Expense type">
        <li class="nav-item"><a class="nav-link active" href="{{ route('expenses.vehicle.index') }}" aria-current="page">Vehicle expenses</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('expenses.business.index') }}">Business expenses</a></li>
    </ul>

    <section class="card mb-3" aria-label="Vehicle expense filters">
        <div class="card-body">
            <h2 class="small fw-semibold mb-3">Filters</h2>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="expense-vehicle-filter">Vehicle</label>
                    <select class="form-select" id="expense-vehicle-filter" data-ajax-table="expenses-dt">
                        <option value="">All vehicles</option>
                        @foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }} {{ $vehicle->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label" for="expense-category-filter">Category</label>
                    <select class="form-select" id="expense-category-filter" data-ajax-table="expenses-dt">
                        <option value="">All categories</option>
                        @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                </div>
                @include('expenses.partials.date-status-filters')
            </div>
        </div>
    </section>

    <section class="card overflow-hidden" aria-label="Vehicle expense list">
        <div class="table-responsive p-2">
            <table id="expenses-dt" class="table table-hover align-middle mb-0 small w-100">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Description</th><th>Category</th><th>Vehicle</th>
                    <th class="text-end">Total</th><th>Payment</th><th class="text-end">Actions</th>
                </tr></thead>
            </table>
        </div>
        <p class="small text-secondary d-md-none mb-0 px-3 pb-3">Swipe or scroll horizontally to see all columns</p>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/expenses.js') }}"></script>
@endpush
