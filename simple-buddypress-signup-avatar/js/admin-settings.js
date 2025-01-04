jQuery(document).ready(function($) {
    $('input[name="compression"]').on('input', function() {
        $('#compression-value').text(this.value);
    });
});
