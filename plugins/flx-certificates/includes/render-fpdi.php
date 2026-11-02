<?php
defined( 'ABSPATH' ) || exit;

use setasign\Fpdi\PdfReader\PageBoundaries;

/** FPDI + TCPDF; pdf-lib alanları sayfanın ham koordinatına çizer, kutunun başlangıcı (MediaBox llx/lly) için düzeltme. */
class FLXS_Fpdi extends \setasign\Fpdi\Tcpdf\Fpdi {
	public function flxs_media_origin( $page_no ) {
		$page = $this->getPdfReader( $this->currentReaderId )->getPage( $page_no );
		$box  = $page->getBoundary( PageBoundaries::CROP_BOX );
		if ( ! $box ) {
			$box = $page->getBoundary( PageBoundaries::MEDIA_BOX );
		}
		return $box ? array( -$box->getLlx(), $box->getLly() ) : array( 0, 0 );
	}
}
