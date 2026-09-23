<?php

namespace App\Services\Crlv;

use RuntimeException;

/**
 * Documento que o leitor não conseguiu interpretar — distinto de um CRLV-e
 * lido e recusado por outro motivo, como exercício vencido.
 */
class CrlvParseException extends RuntimeException {}
