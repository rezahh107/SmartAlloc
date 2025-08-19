(function($){
  $(function(){
    $('#cb-select-all').on('click', function(){
      $('tbody .check-column input[type="checkbox"]').prop('checked', this.checked);
    });

    function notice(msg, cls) {
      $('#smartalloc-notice').remove();
      $('<div id="smartalloc-notice" class="notice ' + cls + '"><p>' + msg + '</p></div>').insertAfter('h1');
    }

    $('.smartalloc-approve').on('click', function(e){
      e.preventDefault();
      if ($(this).data('full')) { return; }
      if (!confirm('Approve entry?')) { return; }
      const entry = $(this).data('entry');
      const mentor = $(this).data('mentor');
      wp.apiFetch({
        path: '/smartalloc/v1/review/' + entry + '/approve',
        method: 'POST',
        data: { mentor_id: mentor }
      }).then(() => {
        notice('Approved', 'notice-success');
      }).catch(err => {
        let msg = 'Error';
        if (err?.data?.error === 'capacity_exceeded') msg = 'Capacity exceeded';
        else if (err?.data?.error === 'entry_locked') msg = 'Entry locked';
        else if (err?.data?.error === 'idempotent_conflict') msg = 'Already processed';
        notice(msg, 'notice-error');
      });
    });

    $('.smartalloc-reject').on('click', function(e){
      e.preventDefault();
      const entry = $(this).data('entry');
      const reason = prompt('Reason code?');
      if (!reason) { return; }
      wp.apiFetch({
        path: '/smartalloc/v1/review/' + entry + '/reject',
        method: 'POST',
        data: { reason: reason }
      }).then(() => {
        notice('Rejected', 'notice-success');
      }).catch(err => {
        let msg = 'Error';
        if (err?.data?.error === 'entry_locked') msg = 'Entry locked';
        notice(msg, 'notice-error');
      });
    });

    $('.smartalloc-defer').on('click', function(e){
      e.preventDefault();
      if (!confirm('Defer entry?')) { return; }
      const entry = $(this).data('entry');
      const note = prompt('Note?') || '';
      wp.apiFetch({
        path: '/smartalloc/v1/review/' + entry + '/defer',
        method: 'POST',
        data: { note: note }
      }).then(() => {
        notice('Deferred', 'notice-success');
      }).catch(err => {
        let msg = 'Error';
        if (err?.data?.error === 'entry_locked') msg = 'Entry locked';
        else if (err?.data?.error === 'idempotent_conflict') msg = 'Already processed';
        notice(msg, 'notice-error');
      });
    });

    $('#smartalloc-bulk-approve').on('click', function(e){
      e.preventDefault();
      const ids = $('tbody .check-column input:checked').map(function(){ return $(this).val(); }).get();
      ids.forEach(function(id){
        const btn = $('.smartalloc-approve[data-entry="' + id + '"]');
        btn.trigger('click');
      });
    });

    $('#smartalloc-bulk-reject').on('click', function(e){
      e.preventDefault();
      const reason = prompt('Reason code?');
      if (!reason) { return; }
      const ids = $('tbody .check-column input:checked').map(function(){ return $(this).val(); }).get();
      ids.forEach(function(id){
        wp.apiFetch({
          path: '/smartalloc/v1/review/' + id + '/reject',
          method: 'POST',
          data: { reason: reason }
        }).catch(() => {});
      });
      notice('Bulk processed', 'notice-success');
    });

    $('#smartalloc-bulk-defer').on('click', function(e){
      e.preventDefault();
      if (!confirm('Defer selected?')) { return; }
      const note = prompt('Note?') || '';
      const ids = $('tbody .check-column input:checked').map(function(){ return $(this).val(); }).get();
      ids.forEach(function(id){
        wp.apiFetch({
          path: '/smartalloc/v1/review/' + id + '/defer',
          method: 'POST',
          data: { note: note }
        }).catch(() => {});
      });
      notice('Bulk processed', 'notice-success');
    });
  });
})(jQuery);


