$(document).ready(function(){
   $('#inicio, #fin').datepicker();
   
   $('#btnexportar').click(function(){
       var html = $('#container').html();
       var win = window.open('algo.php', 'vista', 'width=500,height=800,scrollbar=yes');
       win.document.write(html);
   });
});