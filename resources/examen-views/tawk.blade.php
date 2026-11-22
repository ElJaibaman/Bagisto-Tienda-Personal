@php
    $tawkProperty = config('examen.tawk.property_id');
    $tawkWidget = config('examen.tawk.widget_id', 'default');
@endphp

@if (config('examen.tawk.enabled') && $tawkProperty)
    {{-- Tawk.to: chat en vivo --}}
    <script type="text/javascript">
        var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
        (function () {
            var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
            s1.async = true;
            s1.src = 'https://embed.tawk.to/{{ $tawkProperty }}/{{ $tawkWidget }}';
            s1.charset = 'UTF-8';
            s1.setAttribute('crossorigin', '*');
            s0.parentNode.insertBefore(s1, s0);
        })();
    </script>
@endif
