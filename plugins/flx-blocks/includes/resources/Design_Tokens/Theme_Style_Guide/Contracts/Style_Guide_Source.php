<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Theme_Style_Guide\Contracts;

/**
 * Where the active theme's Style Guide is read from.
 *
 * One implementation reads the Flux theme (Style_Guide_Reader); tests hand the overlay a fake.
 * The snapshot is raw theme data — decoding the palette JSON aside, no interpretation happens here.
 * Picking the active palette set and turning it into token values is the mapper's job, so that
 * logic stays unit-testable without a theme.
 *
 */
interface Style_Guide_Source {

	/**
	 * The theme's Style Guide as raw data, or null when the source is not available (the Flux theme is
	 * not active), so callers can tell "nothing to read" from "read, but empty".
	 *
	 *
	 * @return array{palette: array<string, mixed>, settings: array<string, mixed>, complement?: mixed}|null
	 */
	public function snapshot(): ?array;
}
