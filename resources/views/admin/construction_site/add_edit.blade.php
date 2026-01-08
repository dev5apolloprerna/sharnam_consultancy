@extends('layouts.app')

@section('title', isset($site) ? 'Edit Construction Site' : 'Add Construction Site')

@section('content')

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">

@include('common.alert')
    <div class="card">
        <div class="card-header">
            <h4 class="card-title">{{ isset($site) ? 'Edit' : 'Add' }} Site</h4>
        </div>
        <div class="card-body">
            <form action="{{ isset($site) ? route('admin.construction-site.update', $site->site_id) : route('admin.construction-site.store') }}" method="POST">
                @csrf
                @if(isset($site)) @method('PUT') @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site Name <span style="color:red;">*</span></label>
                        <input type="text" name="site_name" class="form-control" value="{{ old('site_name', $site->site_name ?? '') }}">
                        @if($errors->has('site_name'))<span class="text-danger">{{ $errors->first('site_name') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site Address <span style="color:red;">*</span></label>
                        <input type="text" name="site_address" class="form-control" value="{{ old('site_address', $site->site_address ?? '') }}">
                        @if($errors->has('site_address'))<span class="text-danger">{{ $errors->first('site_address') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site Pincode <span style="color:red;">*</span></label>
                        <input type="number" name="site_pincode" class="form-control" value="{{ old('site_pincode', $site->site_pincode ?? '') }}">
                        @if($errors->has('site_pincode'))<span class="text-danger">{{ $errors->first('site_pincode') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Radius Distance <span style="color:red;">*</span></label>
                        <input type="text" name="site_radious_distance" class="form-control" value="{{ old('site_radious_distance', $site->{'site_radious_distance'} ?? '') }}">
                        @if($errors->has('site_radious_distance'))<span class="text-danger">{{ $errors->first('site_radious_distance') }}</span>@endif
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status <span style="color:red;">*</span></label>
                        <select name="site_status_id" class="form-control">
                            <option value="">Select</option>
                            <option value="1" {{ old('site_status_id', $site->site_status_id ?? '') == 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('site_status_id', $site->site_status_id ?? '') == 0 ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @if($errors->has('site_status_id'))<span class="text-danger">{{ $errors->first('site_status_id') }}</span>@endif
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">{{ isset($site) ? 'Update' : 'Submit' }}</button>
                <a href="{{ route('admin.construction-site.index') }}" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
</div>
</div>
</div>

@endsection
