jQuery(function(){

    var $frm = jQuery('form.elastic_facets');
    if(!$frm.length) return;

    $frm.find('input[type=checkbox]').change(function(){
        this.form.submit();
    });
    $frm.find('input.button').hide();

});