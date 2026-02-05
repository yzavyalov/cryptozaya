<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><img src="{{ asset('images/zaya_logo2.png') }}" style="height: 150px; width: auto;"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('my-wallets') }}">My wallets</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('commission') }}">Commission calculation</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('wallet-balance') }}">Wallet balance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('documentation') }}">Merchant API Documentation</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Merchants
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('new-merchant') }}">Add merchant</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('my-merchants') }}">My merchants</a></li>
                            <li><a class="dropdown-item" href="{{ route('all-merchant-transactions') }}">All transactions</a></li>
                            <li><a class="dropdown-item" href="{{ route('all-deposits') }}">All deposits</a></li>
                        </ul>
                    </li>
                    @hasrole('admin')
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Admin
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('new-user') }}">Add user</a></li>
                            <li><a class="dropdown-item" href="{{ route('all-users') }}">All users</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('new-merchant') }}">Add merchant</a></li>
                            <li><a class="dropdown-item" href="{{ route('my-merchants') }}">My merchants</a></li>
                            <li><a class="dropdown-item" href="{{ route('all-merchant-transactions') }}">All transactions</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('operations') }}">All operations</a></li>
                            <li><a class="dropdown-item" href="{{ route('currencies') }}">Currencies</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('all-users-balance') }}">All  users' balances</a></li>
                            <li><a class="dropdown-item" href="{{ route('all-merchants-balance') }}">All merchants' balances</a></li>
                            <li><a class="dropdown-item" href="{{ route('my-balance') }}">My balance</a></li>
                            <li><a class="dropdown-item" href="{{ route('my-wallets') }}">My wallets</a></li>
                            <li><a class="dropdown-item" href="{{ route('show-keys') }}">show keys</a></li>
                        </ul>
                    </li>
                    @endhasrole
                </ul>
            </div>
        </div>
    </nav>
</nav>
