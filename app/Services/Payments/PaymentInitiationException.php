<?php

namespace App\Services\Payments;

use RuntimeException;

/** The gateway refused to start a payment (e.g. a duplicate tran_id, a validation error). */
class PaymentInitiationException extends RuntimeException {}
