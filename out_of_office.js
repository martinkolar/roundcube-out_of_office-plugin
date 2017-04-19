/**
 * Out of Office plugin script
 *
 */

window.rcmail && rcmail.addEventListener('init', function(evt) {
    rcmail.register_command('plugin.out_of_office-save', function() {
        var checkbox_oooactivate = rcube_find_object('_oooactivate'), 
            input_ooosubject = rcube_find_object('_ooosubject'),
            text_ooobody = rcube_find_object('_ooobody');

      if (checkbox_oooactivate && checkbox_oooactivate.checked == true) { 
          if (input_ooosubject && input_ooosubject.value == '') {
              alert(rcmail.get_label('noooosubject', 'out_of_office'));
              input_ooosubject.focus();
          }
          else if (text_ooobody && text_ooobody.value == '') {
              alert(rcmail.get_label('noooobody', 'out_of_office'));
              text_ooobody.focus();
          }
          else {
            rcmail.gui_objects.out_of_office_form.submit();
          }
      }
      else {
          rcmail.gui_objects.out_of_office_form.submit();
      }
    }, true);

    $('input:not(:hidden):first').focus();
});
