<?php
declare(strict_types=1);

// Decisión de arquitectura documentada (auditoría Sección 4.1, CAMBIOS_FASE5.md):
// este proyecto NO separa controlador/vista. Cada página en public/*/*.php contiene
// su propia lógica de orquestación y llama directo a los modelos en src/modelos/.
// Esta clase queda vacía a propósito — no es código muerto por descuido, es una
// decisión de alcance para un equipo de 2 personas con fecha de entrega fija.
// Si el proyecto crece después de octubre y el equipo se amplía, este es el lugar
// natural para mover la lógica repetida entre páginas de distintos roles.
class MetaControlador {}
