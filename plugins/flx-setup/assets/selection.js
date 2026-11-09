/* Native form controls still submit correctly without JavaScript. */
document.querySelectorAll('input[name="selection_present"]').forEach(function (marker) {
  var form = marker.form;
  var rows = Array.from(form.querySelectorAll('[data-flxk-select]'));
  var masters = Array.from(form.querySelectorAll('[data-flxk-all]'));
  function refresh() {
    masters.forEach(function (master) {
      var group = rows.filter(function (row) { return row.dataset.flxkSelect === master.dataset.flxkAll; });
      var checked = group.filter(function (row) { return row.checked; }).length;
      master.checked = group.length > 0 && checked === group.length;
      master.indeterminate = checked > 0 && checked < group.length;
      master.disabled = group.length === 0;
    });
    var count = rows.filter(function (row) { return row.checked; }).length;
    form.querySelectorAll('[data-flxk-apply]').forEach(function (button) {
      button.textContent = button.dataset.label.replace('%d', String(count));
      button.disabled = count === 0;
    });
  }
  masters.forEach(function (master) {
    master.addEventListener('change', function () {
      rows.forEach(function (row) { if (row.dataset.flxkSelect === master.dataset.flxkAll) row.checked = master.checked; });
      refresh();
    });
  });
  rows.forEach(function (row) { row.addEventListener('change', refresh); });
  refresh();
});
