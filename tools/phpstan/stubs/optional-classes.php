<?php
/**
 * Legacy PHPStan: base classes of optional integrations (they only exist when that library or builder is loaded).
 * A class extending an unknown class cannot be ignored or baselined, so the parent is declared here.
 */

namespace {
	class FPDF {}
}

namespace Elementor {
	abstract class Widget_Base {}
}
