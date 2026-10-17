<aside class="app-sidebar bg-body-tertiary shadow">
    <!--begin::Sidebar Brand-->
    <div class="sidebar-brand">
        <a href="{{ route('home') }}" class="brand-link">
            @if (file_exists(public_path('images/brand.png')))
                <img src="{{ asset('images/brand.png') }}" alt="{{ config('app.name') }}"
                    class="brand-image opacity-75 shadow" />
            @else
                <span class="brand-text fw-semibold">{{ config('app.name') }}</span>
            @endif
        </a>
    </div>
    <!--end::Sidebar Brand-->
    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            {{-- Itens vêm de config/menu.php, filtrados por App\View\Menu. --}}
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                aria-label="Menu principal" data-accordion="false" id="navigation">
                @foreach ($menu as $entrada)
                    @isset($entrada['itens'])
                        <li class="nav-item {{ $entrada['ativo'] ? 'menu-open' : '' }}">
                            <a href="#" class="nav-link {{ $entrada['ativo'] ? 'active' : '' }}">
                                <i class="nav-icon {{ $entrada['icone'] }}"></i>
                                <p>
                                    {{ $entrada['titulo'] }}
                                    <i class="nav-arrow bi bi-chevron-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                @foreach ($entrada['itens'] as $item)
                                    <li class="nav-item">
                                        <a href="{{ $item['url'] }}" class="nav-link {{ $item['ativo'] ? 'active' : '' }}">
                                            <i class="nav-icon {{ $item['icone'] }}"></i>
                                            <p>{{ $item['titulo'] }}</p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a href="{{ $entrada['url'] }}" class="nav-link {{ $entrada['ativo'] ? 'active' : '' }}">
                                <i class="nav-icon {{ $entrada['icone'] }}"></i>
                                <p>{{ $entrada['titulo'] }}</p>
                            </a>
                        </li>
                    @endisset
                @endforeach
            </ul>
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->
</aside>
