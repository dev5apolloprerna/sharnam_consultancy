<!-- ========== App Menu ========== -->
    <div class="app-menu navbar-menu">
        <div id="scrollbar">
            <div class="container-fluid">
                <div id="two-column-menu"></div>
                <ul class="navbar-nav" id="navbar-nav">
                    <li class="menu-title"><span data-key="t-menu"></span></li>
                     <li class="nav-item">
                        <a class="nav-link menu-link @if (request()->routeIs('home')) {{ 'active' }} @endif"
                            href="{{ route('home') }}">
                            <i class="mdi mdi-speedometer"></i>
                            <span data-key="t-dashboards">Dashboards</span>
                        </a>
                    </li>
                        <!-- Category -->
                        <li class="nav-item">
                            <a href="{{ route('admin.construction-site.index') }}" class="nav-link {{ request()->is('admin/construction-site*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-hard-hat"></i>Construction Sites
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.employee.index') }}" class="nav-link {{ request()->is('admin/employee*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-tie"></i>Employees
                            </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.vehicle.index') }}" class="nav-link {{ request()->is('admin/vehicle*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-car-side"></i>
                                Vehicles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('accessories.index') }}"
                               class="nav-link {{ request()->is('admin/accessories*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-cogs"></i>
                                Accessories
                            </a>
                        </li>

                </ul>
            </div>
            <!-- Sidebar -->
        </div>

        <div class="sidebar-background"></div>
    </div>