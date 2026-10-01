<?php

namespace App\Exceptions;

use RuntimeException;

/** Operación que el dominio no permite; el mensaje se muestra tal cual al usuario. */
class ReglaDeNegocio extends RuntimeException {}
