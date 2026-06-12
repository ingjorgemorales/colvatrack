@extends('emails.layout')
@section('content')
<h2 style="margin:0 0 16px;color:#123f6e;font-size:20px">¡Bienvenido a ColvaTrack!</h2>
<p style="margin:0 0 12px">Hola <strong>{{ $name }}</strong>,</p>
<p style="margin:0 0 12px">Se ha creado una cuenta para ti en el sistema <strong>ColvaTrack</strong> de Colvatel S.A.</p>
<p style="margin:0 0 8px">Tus credenciales de acceso son:</p>
<table role="presentation" style="margin:16px 0;padding:16px;background-color:#f8fafc;border-radius:6px;font-size:14px">
<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#64748b">Usuario:</td><td style="padding:4px 0;font-weight:700;color:#0f172a">{{ $email }}</td></tr>
<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#64748b">Contraseña:</td><td style="padding:4px 0;font-weight:700;color:#0f172a">{{ $password }}</td></tr>
</table>
<p style="margin:0 0 8px">Ingresa en: <a href="{{ url('/login') }}" style="color:#123f6e;font-weight:600">{{ url('/login') }}</a></p>
<p style="margin:0 0 12px;padding:12px;background-color:#fef3c7;border-radius:6px;font-size:13px;color:#92400e">⚠️ <strong>Importante:</strong> Al iniciar sesión por primera vez, el sistema te pedirá cambiar tu contraseña por una que solo tú conozcas.</p>
<p style="margin:0">Si tienes dudas, contacta al área de TI.</p>
<p style="margin:16px 0 0">Atentamente,<br><strong>Equipo ColvaTrack - Colvatel S.A.</strong></p>
@endsection
