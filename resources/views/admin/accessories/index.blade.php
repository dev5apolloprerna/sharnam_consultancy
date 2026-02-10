@extends('layouts.app')

@section('title', 'Accessories')

@section('content')

<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            @include('common.alert')

            <div class="row">

                {{-- ADD FORM --}}
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h5>Add Accessories</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('accessories.store') }}">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">
                                        Accessories Name <span style="color:red;">*</span>
                                    </label>
                                    <input type="text" name="accessories_name" class="form-control">

                                    @if($errors->has('accessories_name'))
                                        <span class="text-danger">
                                            {{ $errors->first('accessories_name') }}
                                        </span>
                                    @endif
                                </div>

                                <button class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- LISTING --}}
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h5>Accessories List</h5>
                            <button class="btn btn-danger btn-sm" id="bulkDeleteBtn">
                                <i class="fas fa-trash"></i> Bulk Delete
                            </button>
                        </div>

                        <div class="card-body table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="checkAll"></th>
                                        <th>Accessories Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accessories as $row)
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="rowCheck" value="{{ $row->accessories_id }}">
                                        </td>
                                        <td>{{ $row->accessories_name }}</td>
                                        <td>
                                            <i class="fas fa-edit text-primary editBtn"
                                               data-id="{{ $row->accessories_id }}"
                                               style="cursor:pointer"></i>
                                            <a href="{{ route('accessories.delete',$row->accessories_id) }}">
                                                <i class="fas fa-trash text-danger singleDelete"
                                                       data-id="{{ $row->accessories_id }}"
                                                       style="cursor:pointer"></i>

                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            {{ $accessories->links() }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal fade" id="editModal">
    <div class="modal-dialog">
        <form method="POST" id="editForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Edit Accessories</h5>
                </div>
                <div class="modal-body">
                    <input type="text" name="accessories_name" id="edit_name" class="form-control">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary btn-sm">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
$('#checkAll').click(function(){
    $('.rowCheck').prop('checked', this.checked);
});

$('#bulkDeleteBtn').click(function(){
    let ids = [];
    $('.rowCheck:checked').each(function(){
        ids.push($(this).val());
    });

    if(ids.length === 0){
        alert('Please select records');
        return;
    }

    if(confirm('Are you sure to delete selected records?')){
        $.post("{{ route('accessories.bulkDelete') }}", {
            _token: "{{ csrf_token() }}",
            ids: ids
        }, function(){
            location.reload();
        });
    }
});

$('.editBtn').click(function(){
    let id = $(this).data('id');

    $.get("{{ url('admin/accessories/edit') }}/"+id, function(data){
        $('#edit_name').val(data.accessories_name);
        $('#editForm').attr('action',"{{ url('admin/accessories/update') }}/"+id);
        $('#editModal').modal('show');
    });
});
</script>
<script>
$(document).on('click', '.singleDelete', function () {

    let id = $(this).data('id');

    if(confirm('Are you sure you want to delete this record?')) {
        $.ajax({
            url: "{{ url('admin/accessories/delete') }}/" + id,
            type: "DELETE",
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function () {
                location.reload();
            }
        });
    }
});
</script>

@endsection

