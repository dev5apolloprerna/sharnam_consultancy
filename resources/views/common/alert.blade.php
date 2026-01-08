{{-- Message --}}
@if (Session::has('success'))
    <!-- Success Alert -->
    <div class="alert alert-primary alert-dismissible alert-solid alert-label-icon fade show" role="alert" id="success-alert">
        <i class="ri-user-smile-line label-icon"></i>
        <strong>Success !</strong> {{ session('success') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (Session::has('error'))
    <!-- Danger Alert -->
    <div class="alert alert-danger alert-dismissible alert-solid alert-label-icon fade show" role="alert" id="error-alert">
        <i class="ri-error-warning-line label-icon"></i>
        <strong>Error !</strong> {{ session('error') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script type="text/javascript">

// $(document).ready(function() 
// {
//     $("#success-alert").fadeTo(2000, 3000).slideUp(3000, function() {
//       $("#success-alert").slideUp(3000);
//     });
  
//     $("#error-alert").fadeTo(2000, 3000).slideUp(3000, function() {
//       $("#error-alert").slideUp(3000);
//     });
  
// });
    

</script>
