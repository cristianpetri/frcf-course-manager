(function($){
$(document).on("change", ".frcf-location-filter", function(){
    var val = $(this).val();
    var $cards = $(".frcf-course-card");
    if(!val){ $cards.show(); return; }
    $cards.hide().filter(function(){
        return $(this).data("location") == val;
    }).show();
});
})(jQuery);
