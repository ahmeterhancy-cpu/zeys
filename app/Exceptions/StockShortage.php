<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Rezervasyon sırasında bir satırın stoğu yetmediğinde fırlatılır ve
 * işlemi geri sardırır. Dışarı sızmaz — OrderStock::reserve() yakalar.
 */
class StockShortage extends RuntimeException {}
