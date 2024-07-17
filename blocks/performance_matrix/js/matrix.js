$(document).ready(function(){
  
    $(document).on('change', '.form-control.w-100.matrix_elem', function(){
        var perf = $('#performance').val(); 
        if(perf == 1){
            $('.ptype').show();
        }else{
            $('.ptype').hide();
        }
        var ptype = $('#performancetype').val(); 
        var radio_filter = $('input[name="radio_filter"]:checked').val();      
        var month = $('#month').val();
        var year = $('#year').val();
        var userid = $('#userid').val();
        $.ajax({
            method: "POST",
            dataType: "json",
            url: M.cfg.wwwroot + "/blocks/performance_matrix/ajax.php",
            data: { ptype : ptype,radio_filter : radio_filter, month : month, year : year,userid :userid,performance : perf },
            success: function(data){
               $('.block_performance_matrix_filter').html(data.html);
               $('head').append(data.javascript);
            }
        });
    });

    $(document).on('click', '.radio_filter', function(){
        var radio_filter = $(this).val();  
        var perf = $('#performance').val();      
        var ptype = $('#performancetype').val(); 
        var userid = $('#userid').val();
        $.ajax({
            method: "POST",
            dataType: "json",
            url: M.cfg.wwwroot + "/blocks/performance_matrix/ajax.php",
            data: { ptype : ptype,radio_filter : radio_filter, userid :userid,performance : perf},
            success: function(data){
               $('.block_performance_matrix_filter').html(data.html);
               $('head').append(data.javascript);
            }
        });
    });
  
});
