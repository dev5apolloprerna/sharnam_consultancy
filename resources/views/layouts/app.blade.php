<!doctype html>
<html lang="en" data-layout="horizontal" data-layout-style="default" data-layout-position="fixed" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="sm-hover" data-layout-width="fluid">

{{-- Include Head --}}
@include('common.head')

<body>

    <!-- Begin page -->
    <div id="layout-wrapper">

        <!-- Topbar -->
        @include('common.header')
        <!-- End of Topbar -->

        <!-- Sidebar -->
        @include('common.sidebar')
        <!-- End of Sidebar -->

        @yield('content')

        @include('common.footer')

    </div>

    @include('common.footerjs')

    @yield('scripts')

</body>

</html>
