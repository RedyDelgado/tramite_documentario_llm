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

Los documentos del trámite van adjuntos.
@if ($atencion)

**Para responder, pulse «Responder» en este mismo correo y no cambie el asunto.** Adjunte lo que corresponda. Si escribe un correo nuevo, su respuesta no quedará enlazada al trámite.
@endif

Código del trámite: {{ $expediente->codigo ?? 'sin código' }}
</x-mail::message>
