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

    var hasValidPhone = function(rawValue){
      var raw = (rawValue || '').trim();
      var onlyDigits = raw.replace(/[^0-9]/g, '');
      var allowed = /^[+0-9\s\-\(\)]{2,}$/;
      return raw !== '' && allowed.test(raw) && onlyDigits.length >= 2;
    };

    var validateField = function(field){
      if(!field) return true;
      if(field.id === 'fc_telefono'){
        field.setCustomValidity('');
      }
      var valid = typeof field.checkValidity === 'function' ? field.checkValidity() : true;
      if(valid && field.id === 'fc_telefono' && !hasValidPhone(field.value)){
        field.setCustomValidity('Ingresá un teléfono válido');
        valid = false;
      }
      field.classList.toggle('is-invalid', !valid);
      field.setAttribute('aria-invalid', valid ? 'false' : 'true');
      return valid;
    };

    var fields = Array.prototype.slice.call(form.querySelectorAll('.form-control'));
    fields.forEach(function(field){
      var revalidateIfNeeded = function(){
        if(
          form.classList.contains('was-validated') ||
          field.classList.contains('is-invalid') ||
          field.getAttribute('aria-invalid') === 'true'
        ){
          validateField(field);
        }
      };
      field.addEventListener('input', revalidateIfNeeded);
      field.addEventListener('blur', function(){
        if((field.value || '').trim() !== '' || form.classList.contains('was-validated') || field.classList.contains('is-invalid')){
          validateField(field);
        }
      });
    });

    form.addEventListener('submit', function(e){
      var formValid = true;
      var firstInvalid = null;

      fields.forEach(function(field){
        if(!validateField(field)){
          formValid = false;
          if(!firstInvalid){
            firstInvalid = field;
          }
        }
      });

      if (!formValid) {
        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) {
          e.stopImmediatePropagation();
        }
        form.classList.add('was-validated');
        if (firstInvalid) {
          if (typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
          }
          if (typeof firstInvalid.reportValidity === 'function') {
            firstInvalid.reportValidity();
          }
        } else if (typeof form.reportValidity === 'function') {
          form.reportValidity();
        }
        return false;
      }

      var tel = document.getElementById('fc_telefono');
      var telFull = document.getElementById('fc_telefono_full');
      if (telFull) {
        telFull.value = tel ? (tel.value || '').trim() : '';
      }

      submitUI(true);
      return true;
    });
  });
})();
