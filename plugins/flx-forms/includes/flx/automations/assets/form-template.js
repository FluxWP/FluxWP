/* Flux Forms · "Sapling Donation" template card on EFB's Create screen.
   Added from outside admin-efb.js: pushes a card into boxs_efb and wraps create_form_by_type_emsfb. */
(function () {
  'use strict';
  var D = window.FLXF_FORM_TPL;
  if (!D || !Array.isArray(D.structure) || typeof boxs_efb === 'undefined' || typeof window.create_form_by_type_emsfb !== 'function') return;
  var ID = 'flxfSaplingDonation';
  if (!boxs_efb.some(function (b) { return b.id === ID; })) {
    /* Right after the blank payment form card. */
    var at = boxs_efb.findIndex(function (b) { return b.id === 'payment'; });
    boxs_efb.splice(at >= 0 ? at + 1 : boxs_efb.length, 0, { id: ID, title: D.title, desc: D.desc, status: true, icon: 'bi-heart', tag: 'all payment new', pro: false });
  }
  var original = window.create_form_by_type_emsfb;
  window.create_form_by_type_emsfb = function (id, s) {
    if (id !== ID) return original.apply(this, arguments);
    var json = JSON.parse(JSON.stringify(D.structure));
    state_page_efb = 'create';
    localStorage.setItem('efb_auto_save', 0);
    if (s !== 'pre') {
      document.getElementById('header-efb').innerHTML = '';
      document.getElementById('content-efb').innerHTML = '';
    }
    form_type_emsFormBuilder = 'payment';
    sessionStorage.setItem('valj_efb', JSON.stringify(json));
    valj_efb = json;
    valueJson_ws_p = json;
    formName_Efb = form_type_emsFormBuilder;
    if (s === 'npreview') {
      creator_form_builder_Efb();
      setTimeout(function () { editFormEfb(); }, 200);
    } else {
      previewFormEfb('pre'); // same as EFB's own branch for 'pre' / 'preview'
    }
  };
})();
