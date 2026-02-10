@extends('layouts.app')

@section('title', 'Assign Accessories')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            @include('common.alert')

            {{-- Main Card --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assign Accessories</h5>
                    <a href="{{ url('/admin/construction-site') }}" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>

                <div class="card-body">

                    {{-- Form Card --}}
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.construction-site.accessories.save') }}">
                                @csrf
                            
                                <input type="hidden" name="site_id" value="{{ $site->site_id }}">
                            
                                <div class="row g-3 align-items-end">
                            
                                    {{-- Accessories --}}
                                    <div class="col-md-4">
                                        <label class="form-label">
                                            Accessories <span style="color:red;">*</span>
                                        </label>
                                        <select class="form-control" name="accessories_id" required>
                                            <option value="">-- Select Accessories --</option>
                                            @foreach($accessories as $acc)
                                                <option value="{{ $acc->accessories_id }}">
                                                    {{ $acc->accessories_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                            
                                    {{-- Qty --}}
                                    <div class="col-md-2">
                                        <label class="form-label">
                                            Qty <span style="color:red;">*</span>
                                        </label>
                                        <input type="number" name="qty" class="form-control" min="1" required>
                                    </div>
                            
                                    {{-- Date --}}
                                    <div class="col-md-3">
                                        <label class="form-label">
                                            Date <span style="color:red;">*</span>
                                        </label>
                                        <input type="date" name="date" class="form-control" required>
                                    </div>
                            
                                    <div class="col-md-3">
                                        <button class="btn btn-primary w-100">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                            
                                </div>
                            </form>

                        </div>
                    </div>

                    {{-- Assigned List --}}
                    <div class="card-header">
                        <h6 class="mb-0">Assigned Accessories List</h6>
                    </div>

                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-primary">
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Accessories</th>
                                    <th>Qty</th>
                                    <th>Date</th>
                                    <th style="width:120px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                
                                    <td>{{ $item->accessories_name }}</td>
                                
                                    <td>{{ $item->qty }}</td>
                                
                                    <td>{{ date('d-m-Y',strtotime($item->date)) }}</td>
                                
                                    <td>
                                        <form method="POST"
                                              action="{{ route('admin.construction-site.accessories.delete', $item->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Delete this record?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">
                                        No accessories assigned.
                                    </td>
                                </tr>
                                @endforelse
                                </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection
