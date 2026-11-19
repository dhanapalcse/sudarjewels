<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation whose message is safe to show to the acting staff member or affiliate.
 */
class BusinessRuleException extends RuntimeException {}
