@extends('emails.layout')
@section('content')
{!! nl2br(e($body)) !!}
<p style="margin:16px 0 0;font-size:13px;color:#64748b">Este es un mensaje automático del sistema ColvaTrack. No respondas a este correo.</p>
@endsection
