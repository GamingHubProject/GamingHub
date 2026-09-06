<?php

namespace App\Experience;

use RuntimeException;

/**
 * A theme archive that can't be trusted or can't be understood.
 *
 * Its message is written for an admin looking at a file picker, not for a
 * log: every throw site says what was wrong with the archive and, where
 * there is one, what a correct archive looks like instead.
 */
class ThemePackageException extends RuntimeException
{
}
