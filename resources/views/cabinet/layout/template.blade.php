@include('cabinet.layout.header')


@include('cabinet.layout.navbar')

    <!-- Content Wrapper. Contains page content -->
<body class="bg-light">
<div class="container min-vh-100 d-flex justify-content-center pt-5">
    <div class="row w-100 justify-content-center">
        <div class="col-12 px-3">

            <div class="mx-auto" style="max-width: 1200px;">
                <div class="text-center text-muted mb-3">
                    @yield('content')
                </div>
            </div>

        </div>
    </div>
</div>



@include('cabinet.layout.footer')
