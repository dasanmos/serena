<?php
// Panel lateral de marca para las pantallas públicas de acceso (registro, recuperación,
// restablecer, verificación en 2 pasos, consentimiento). Se incluye DENTRO de .card-login,
// antes de .login-form-lado. Variables opcionales: $marcaTitulo, $marcaTexto.
$marcaTitulo = $marcaTitulo ?? 'Bienestar emocional para tu colegio';
$marcaTexto = $marcaTexto ?? 'Cuerpo • Mente • Bienestar. Acompañamiento cercano para estudiantes, familias y equipos docentes.';
?>
<aside class="login-marca-lado">
    <div>
        <a href="index.php"><img src="assets/img/logo/logo.png" alt="Serena" class="logo-login"></a>
        <h2><?= htmlspecialchars($marcaTitulo) ?></h2>
        <p><?= htmlspecialchars($marcaTexto) ?></p>
    </div>
    <ul class="login-puntos">
        <li><span>💛</span> Seguimiento emocional diario</li>
        <li><span>🔔</span> Alertas tempranas para actuar a tiempo</li>
        <li><span>🔒</span> Tus datos protegidos, con verificación en 2 pasos</li>
    </ul>
</aside>
