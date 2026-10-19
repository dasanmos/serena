<?php
declare(strict_types=1);

function validarCorreo(string $correo): bool
{
    return (bool) filter_var($correo, FILTER_VALIDATE_EMAIL);
}

// Regla de contraseña de Serena: 8 a 64 caracteres, al menos una mayúscula,
// una minúscula, un dígito y un carácter especial. Se centraliza aquí el
// regex para que backend y frontend (requisitosPassword.js) describan
// exactamente los mismos requisitos.
const REGEX_PASSWORD = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}$/';

function validarPassword(string $password): bool
{
    return (bool) preg_match(REGEX_PASSWORD, $password);
}

// Mensaje único para mostrar en los formularios cuando validarPassword() falla,
// para no repetir el texto de los requisitos en cada controlador.
function mensajeRequisitosPassword(): string
{
    return 'La contraseña debe tener entre 8 y 64 caracteres, e incluir al menos '
        . 'una mayúscula, una minúscula, un número y un carácter especial.';
}

// Genera una contraseña temporal aleatoria que cumple REGEX_PASSWORD, para los
// flujos de importación por CSV donde no se especificó una contraseña propia.
function generarPasswordTemporalValida(): string
{
    $especiales = '!@#$%&*?';
    do {
        $pass = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 1)
            . substr(str_shuffle('abcdefghijkmnpqrstuvwxyz'), 0, 1)
            . substr(str_shuffle('23456789'), 0, 1)
            . substr(str_shuffle($especiales), 0, 1)
            . substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4);
        $pass = str_shuffle($pass);
    } while (!validarPassword($pass));
    return $pass;
}

function validarEdadMinima(string $fechaNacimiento, int $edadMinima = 10): bool
{
    $nacimiento = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    if (!$nacimiento) {
        return false;
    }
    $edad = $nacimiento->diff(new DateTime())->y;
    return $edad >= $edadMinima;
}

// PHP quitó strftime() en 8.1+, así que se arma a mano en vez de depender de la
// configuración regional del servidor (que en hosting compartido no siempre trae
// el locale es_CO instalado).
function fechaLarga(string $fechaYmd): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
              'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($fechaYmd);
    $diaSemana = $dias[(int) date('w', $t)];
    $dia = (int) date('j', $t);
    $mes = $meses[(int) date('n', $t)];
    return "{$diaSemana} {$dia} de {$mes}";
}
