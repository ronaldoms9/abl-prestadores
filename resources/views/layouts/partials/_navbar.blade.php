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

             {{-- Fatia 8: indicador de falhas de integração. --}}

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
