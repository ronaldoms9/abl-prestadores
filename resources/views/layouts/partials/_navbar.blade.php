 <nav class="app-header  navbar navbar-expand bg-body">
     <!--begin::Container-->
     <div class="container-fluid">
         <!--begin::Start Navbar Links-->
         <ul class="navbar-nav">
             <li class="nav-item">
                 <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                     <i class="bi bi-list"></i>
                 </a>
             </li>
             {{-- <li class="nav-item d-none d-md-block"><a href="#" class="nav-link">Home</a></li>
            <li class="nav-item d-none d-md-block"><a href="#" class="nav-link">Contact</a></li> --}}
         </ul>
         <!--end::Start Navbar Links-->
         <!--begin::End Navbar Links-->
         <ul class="navbar-nav ms-auto">
             <!--begin::Navbar Search-->

             <!--end::Navbar Search-->

             @if ($indicadorIntegracao)
                 <!--begin::Indicador de integrações (fatia 8)-->
                 <li class="nav-item dropdown" id="indicador-integracao">
                     <a class="nav-link" data-bs-toggle="dropdown" href="#" title="Integrações com o UAU">
                         <i class="bi bi-bell"></i>
                         @if ($indicadorIntegracao['falhas'] > 0)
                             <span class="navbar-badge badge text-bg-danger">{{ $indicadorIntegracao['falhas'] }}</span>
                         @endif
                     </a>
                     <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                         <span class="dropdown-item dropdown-header">Integrações com o UAU</span>
                         <div class="dropdown-divider"></div>
                         <a href="{{ route('integracao.index', ['situacao' => 'sem-solucao', 'de' => now()->subDay()->toDateString()]) }}" class="dropdown-item">
                             <i class="bi bi-exclamation-triangle me-2 {{ $indicadorIntegracao['falhas'] > 0 ? 'text-danger' : 'text-muted' }}"></i>
                             @if ($indicadorIntegracao['falhas'] === 0)
                                 Nenhuma falha sem solução nas últimas 24 h
                             @else
                                 {{ $indicadorIntegracao['falhas'] }} {{ $indicadorIntegracao['falhas'] === 1 ? 'falha' : 'falhas' }} sem solução nas últimas 24 h
                             @endif
                         </a>
                         <div class="dropdown-divider"></div>
                         <span class="dropdown-item small text-muted">
                             <i class="bi bi-arrow-repeat me-2"></i>
                             Última sincronização concluída:
                             {{ $indicadorIntegracao['ultima']?->format('d/m/Y H:i') ?? 'nenhuma' }}
                         </span>
                         <div class="dropdown-divider"></div>
                         <a href="{{ route('integracao.index') }}" class="dropdown-item dropdown-footer">Ver todas as chamadas</a>
                     </div>
                 </li>
                 <!--end::Indicador de integrações-->
             @endif

             <!--begin::Fullscreen Toggle-->
             <li class="nav-item">
                 <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                     <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                     <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                 </a>
             </li>
             <!--end::Fullscreen Toggle-->
             <!--begin::User Menu Dropdown-->
             <li class="nav-item dropdown user-menu">
                 <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                     <img src="{{ auth()->user()->photo_url }}"
                         class="user-image rounded-circle shadow" alt="{{ auth()->user()->name }}" />
                     <span class="d-none d-md-inline">{{ auth()->user()->firstName()}}</span>
                 </a>
                 <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                     <!--begin::User Image-->
                     <li class="user-header text-bg-primary">
                         <img src="{{ auth()->user()->photo_url }}" class="rounded-circle shadow"
                             alt="{{ auth()->user()->name }}" />
                         <p>
                             {{  auth()->user()->name    }}
                             {{-- <small>Member since Nov. 2023</small> --}}
                         </p>
                     </li>
                     <!--end::User Image-->
                     <!--begin::Menu Body-->

                     <!--end::Menu Body-->
                     <!--begin::Menu Footer-->
                     <li class="user-footer">
                         <a href="{{ route('profile.edit') }}" class="btn btn-default btn-flat">{{ __('Meu perfil') }}</a>
                         <a href="{{ route('logout') }}" class="btn btn-default btn-flat float-end"
                             onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                             <i class="bi bi-box-arrow-right"></i>
                             <span>{{ __('Sair') }}</span>
                         </a>
                     </li>
                     <!--end::Menu Footer-->
                 </ul>
             </li>
             <!--end::User Menu Dropdown-->
         </ul>
         <!--end::End Navbar Links-->
     </div>
     <!--end::Container-->
 </nav>
