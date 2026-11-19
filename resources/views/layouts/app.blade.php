<!DOCTYPE html>
<html lang="en-IN">
<head>
    @include('partials.head')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col">
    @include('partials.header')
    @include('partials.flash')
    <main id="main" class="flex-1">
        @yield('content')
    </main>
    @include('partials.footer')
    @stack('scripts')
</body>
</html>
