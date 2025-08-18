(function($){
  $(function(){
    $('#cb-select-all').on('click', function(){
      $('tbody .check-column input[type="checkbox"]').prop('checked', this.checked);
    });
  });
})(jQuery);
