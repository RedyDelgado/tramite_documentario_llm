<x-mail::message>
Hola, {{ $destinatario->name }}:

@if ($atencion)
Se derivó a **{{ $atencion->area->nombre }}** el siguiente trámite para su atención{{ $atencion->fecha_limite ? ', con plazo hasta el '.$atencion->fecha_limite->format('d/m/Y') : '' }}.
@else
Le enviamos en copia, para conocimiento, el siguiente trámite. No requiere que lo atienda.
@endif

- **Asunto:** {{ \Illuminate\Support\Str::limit($expediente->asunto, 120) }}
- **Remitente:** {{ $expediente->remitente_nombre ?? $expediente->remitente_email ?? '—' }}
- **Expediente:** {{ $expediente->numero_registro ?? 'Sin número' }}
@if ($movimiento->instruccion)
- **Instrucción:** {{ $movimiento->instruccion }}
@endif
@if ($movimiento->nota)
- **Indicaciones:** {{ $movimiento->nota }}
@endif

Los documentos del trámite van adjuntos.{{ $atencion ? ' Para responder, conteste este correo y adjunte lo que corresponda.' : '' }}

Código del trámite: {{ $expediente->codigo ?? 'sin código' }}
</x-mail::message>
