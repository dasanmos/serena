<?php
// Este archivo vive en la raíz real del sitio (fuera de public/), necesario porque
// InfinityFree (y la mayoría de hosting compartido gratuito) no permite fijar el
// document root a la carpeta public/ — todo el proyecto queda dentro de htdocs/
// como hermanos: public/, src/, sql/. Sin este archivo, entrar al dominio sin
// escribir "/public/" a mano no lleva a ningún lado.
header('Location: /public/index.php');
exit;
