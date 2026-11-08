/* Native details works without JavaScript; this adds Escape/outside dismissal. */
(function () {
 'use strict';
 document.addEventListener('click',function(e){document.querySelectorAll('.flxl-picker[open]').forEach(function(d){if(!d.contains(e.target))d.open=false;});});
 document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.flxl-picker[open]').forEach(function(d){d.open=false;d.querySelector('summary').focus();});});
 document.addEventListener('toggle',function(e){if(e.target.matches('.flxl-picker[open]'))document.querySelectorAll('.flxl-picker[open]').forEach(function(d){if(d!==e.target)d.open=false;});},true);
}());
