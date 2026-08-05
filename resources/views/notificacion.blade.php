<h2>Mis Notificaciones</h2>

<ul>
    @forelse (auth()->user()->notifications as $notificacion)
        <li>{{ $notificacion->data['mensaje'] }}</li>
    @empty
        <li>No tienes notificaciones.</li>
    @endforelse
</ul>
