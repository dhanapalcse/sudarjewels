<!DOCTYPE html>
<html lang="en-IN">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col @hasSection('hideBottomNav') @else pb-16 lg:pb-0 @endif">
    @include('partials.header')
    @include('partials.flash')
    <main id="main" class="flex-1">
        @yield('content')
    </main>
    @include('partials.footer')
    @hasSection('hideBottomNav')
    @else
        @include('partials.bottom-nav')
    @endif
    @stack('scripts')
</body>
</html>
