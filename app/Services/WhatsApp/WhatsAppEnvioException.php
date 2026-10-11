<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/** El proveedor (p. ej. la Cloud API de Meta) rechazó el mensaje o no se pudo contactar. */
class WhatsAppEnvioException extends RuntimeException
{
}
