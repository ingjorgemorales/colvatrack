@extends('emails.layout')
@section('content')
<h2 style="margin:0 0 16px;color:#123f6e;font-size:20px">Recuperación de contraseña</h2>
<p style="margin:0 0 12px">Hola <strong>{{ $name }}</strong>,</p>
<p style="margin:0 0 12px">Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>ColvaTrack</strong>.</p>
<p style="margin:0 0 16px">Haz clic en el botón de abajo para crear una nueva contraseña:</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px">
<tr><td style="background-color:#123f6e;border-radius:6px;padding:12px 28px">
<a href="{{ $resetUrl }}" style="color:#ffffff;font-size:15px;font-weight:700;text-decoration:none">Restablecer contraseña</a>
</td></tr>
</table>
<p style="margin:0 0 12px;font-size:13px;color:#64748b">Este enlace expirará en {{ $expireMinutes }} minutos.</p>
<p style="margin:0 0 12px;font-size:13px;color:#64748b">Si no solicitaste este cambio, ignora este correo.</p>
<p style="margin:16px 0 0">Atentamente,<br><strong>Equipo ColvaTrack - Colvatel S.A.</strong></p>
@endsection
