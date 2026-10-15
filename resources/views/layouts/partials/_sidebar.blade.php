<aside class="app-sidebar bg-body-tertiary shadow">
    <!--begin::Sidebar Brand-->
    <div class="sidebar-brand">
        <!--begin::Brand Link-->
        <a href="{{ url('') }}" class="brand-link">
            <!--begin::Brand Image-->
            <img src="{{ asset('images/brand.png') }}" alt="{{ config('app.name') }}"
                class="brand-image opacity-75 shadow" />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            {{-- <span class="brand-text fw-light">AdminLTE 4</span> --}}
            <!--end::Brand Text-->
        </a>
        <!--end::Brand Link-->
    </div>
    <!--end::Sidebar Brand-->
    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                aria-label="Main navigation" data-accordion="false" id="navigation">

                <li class="nav-item ">
                    <a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                @permission(['grupo-list', 'user-list'])

                    <li class="nav-item {{ request()->routeIs('user.index') || request()->routeIs('grupo.index') ? 'menu-open ' : '' }}">
                        <a href="#" class="nav-link {{ request()->routeIs('user.index') || request()->routeIs('grupo.index') ? 'active' : '' }}">
                            <i class="nav-icon fa fa-cogs"></i>
                            <p>
                                Configurações
                                <i class="nav-arrow bi bi-chevron-right"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('grupo.index') }}" class="nav-link {{ request()->routeIs('grupo.index') ? 'active' : '' }}">
                                    <i class="nav-icon fa fa-users"></i>
                                    <p>Grupos de Acesso</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('user.index') }}" class="nav-link {{ request()->routeIs('user.index') ? 'active' : '' }}">
                                    <i class="nav-icon fa fa-user"></i>
                                    <p>Usuários</p>
                                </a>
                            </li>

                        </ul>
                    </li>
                    @endpermission

                </ul>
                <!--end::Sidebar Menu-->
            </nav>
        </div>
        <!--end::Sidebar Wrapper-->
    </aside>
