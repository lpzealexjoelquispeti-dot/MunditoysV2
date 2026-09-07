<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invitación a MundiToys</title></head>
<body style="margin:0;background:#fff8f2;font-family:Inter,Arial,sans-serif;color:#16334a">
<table role="presentation" style="width:100%;padding:24px"><tr><td align="center">
<table role="presentation" style="width:100%;max-width:560px;background:#ffffff;border:1px solid #dbeafe;border-radius:20px">
<tr><td style="padding:30px;background:#005ca8;border-radius:20px 20px 0 0;color:#ffffff"><strong style="font-size:26px">★ MundiToys</strong><p style="margin-bottom:0;color:#fed7aa">Construyendo sueños, juntos</p></td></tr>
<tr><td style="padding:30px">
<h1 style="font-size:24px">¡Hola, {{ $nombre }}!</h1>
<p style="line-height:1.7">Te invitamos a formar parte de MundiToys con el rol <strong>{{ $rol === 'Logistica' ? 'Logística' : $rol }}</strong>.</p>
<p style="line-height:1.7">{{ match ($rol) {
    'Administrador' => 'Podrás administrar el sistema, sus usuarios y las operaciones autorizadas.',
    'Inventario' => 'Tu espacio te permitirá trabajar con productos e inventario.',
    'Logistica' => 'Tu espacio te permitirá consultar productos y gestionar inventario según tus permisos.',
    default => 'Podrás consultar el catálogo interno y acceder a las funciones de venta habilitadas.',
} }}</p>
<p>Tu correo de acceso: <strong>{{ $correo }}</strong></p>
<p style="margin:30px 0"><a href="{{ $urlActivacion }}" style="display:inline-block;background:#e90016;color:#ffffff;text-decoration:none;border-radius:12px;padding:15px 22px;font-weight:bold">Activar cuenta y crear contraseña</a></p>
<p style="line-height:1.7;font-size:14px">Este enlace vence en 24 horas y se puede usar una sola vez. Si recibes una nueva invitación, utiliza el enlace más reciente.</p>
<p style="line-height:1.7;font-size:14px">Nadie de MundiToys necesita conocer tu contraseña. Si no esperabas esta invitación, ignórala y comunícate con el administrador.</p>
<p style="font-size:12px;color:#64748b">Si el botón no funciona, copia este enlace en tu navegador:</p>
<p style="font-size:12px;word-break:break-all"><a href="{{ $urlActivacion }}">{{ $urlActivacion }}</a></p>
</td></tr></table>
</td></tr></table></body></html>
