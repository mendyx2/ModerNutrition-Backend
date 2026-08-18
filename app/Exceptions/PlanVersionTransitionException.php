<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a PlanVersion cannot transition between statuses.
 * Typically because allocation_categories percentages do not sum
 * to the required total.
 */
class PlanVersionTransitionException extends RuntimeException
{
    //
}
