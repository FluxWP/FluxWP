<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Registry\Exception;

use RuntimeException;

/**
 * Thrown (under WP_DEBUG) when a declared token has no entry in the baseline document.
 *
 */
final class Missing_Baseline_Entry extends RuntimeException {
}
