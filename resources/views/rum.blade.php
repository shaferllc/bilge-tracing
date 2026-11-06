@if (\Bilge\Tracing\Laravel\RumScript::enabled())
    {!! \Bilge\Tracing\Laravel\RumScript::traceMetaHtml() !!}
    <script src="{{ route('bilge.rum.script') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof BilgeRum === 'undefined') {
                return;
            }
            BilgeRum.init(@json(\Bilge\Tracing\Laravel\RumScript::initConfig()));
        });
    </script>
@endif
