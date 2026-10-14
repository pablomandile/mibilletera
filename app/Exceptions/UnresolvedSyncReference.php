<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Una operación sin conexión apunta a algo creado sin conexión (id "tmp:...")
 * que todavía no se sincronizó. Ver SyncController.
 */
class UnresolvedSyncReference extends RuntimeException {}
