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
                         <li class="nav-item">
                          <a href="{{ route('admin.employee_leave.index') }}"
                             class="nav-link {{ request()->routeIs('admin.employee_leave.*') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-calendar-alt"></i>
                            Employee Leave
                          </a>
                        </li>

                        <li class="nav-item">
                            <a href="{{ route('admin.salary-process.index') }}" class="nav-link {{ request()->is('admin/salary-process*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-money-bill-wave"></i>Salary Process
                            </a>
                        </li>
                        <li class="nav-item">
                                <a class="nav-link" href="#sidebarMore" data-bs-toggle="collapse" role="button"
                                    aria-expanded="true" aria-controls="sidebarMore">
                                    <i class="fa fa-list text-white"></i>Employee Ledger</a>
                                <div class="menu-dropdown collapse show" id="sidebarMore" style="">
                                    <ul class="nav nav-sm flex-column">
                                        @php
                            $seg2 = request()->segment(2); // after /admin
                                        $isCreditMenu = in_array($seg2, ['employee-credit']);
                                    @endphp
                                    <li class="nav-item">
                                        <a href="{{ route('admin.employee-credit.create') }}"
                                           class="nav-link {{ request()->routeIs('admin.employee-credit.create') ? 'active' : '' }}">
                                            <i class="far fa-credit-card nav-icon"></i>
                                            <p>Add Credit</p>
                                        </a>
                                    </li>

                                    <li class="nav-item">
                                        <a href="{{ route('admin.employee-credit.index') }}"
                                           class="nav-link {{ request()->routeIs('admin.employee-credit.index') ? 'active' : '' }}">
                                            <i class="fa fa-list nav-icon"></i>
                                            <p>Ledger List</p>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>


                </ul>
            </div>
            <!-- Sidebar -->
        </div>

        <div class="sidebar-background"></div>
    </div>