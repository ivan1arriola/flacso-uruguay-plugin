(function(){
  document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('fc-form');
    if(!form) return;

    var submitUI = function(isLoading){
      var btn = form.querySelector('button[type="submit"]');
      var text = form.querySelector('.fc-btn-text');
      var spinner = form.querySelector('.fc-btn-spinner');
      if(btn && spinner && text){
        btn.disabled = isLoading;
        spinner.classList.toggle('d-none', !isLoading);
        text.classList.toggle('d-none', isLoading);
      }
    };

    form.addEventListener('submit', function(e){
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        if (form.reportValidity) { form.reportValidity(); }
        return false;
      }

      var tel = document.getElementById('fc_telefono');
      var telFull = document.getElementById('fc_telefono_full');
      var raw = tel ? (tel.value || '').trim() : '';
      if (raw) {
        var onlyDigits = raw.replace(/[^0-9]/g,'');
        var allowed = /^[+0-9\s\-\(\)]{2,}$/;
        if (!allowed.test(raw) || onlyDigits.length < 2) {
          e.preventDefault();
          e.stopPropagation();
          if (tel) {
            tel.classList.add('is-invalid');
            if (tel.reportValidity) { tel.reportValidity(); }
          }
          return false;
        }
        if (telFull) { telFull.value = raw; }
      } else {
        if (telFull) { telFull.value = ''; }
        e.preventDefault();
        e.stopPropagation();
        if (tel) {
          tel.classList.add('is-invalid');
          if (tel.reportValidity) { tel.reportValidity(); }
        }
        return false;
      }

      submitUI(true);
      return true;
    });
  });
})();
