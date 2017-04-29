
var $jQema = jQuery.noConflict();

$jQema(document).ready(function() {
    var mobassist_login = $jQema("#mobassist_login");
    var mobassist_pass = $jQema("#mobassist_pass");

    _old_login = $jQema(mobassist_login).val();
    _old_pass = $jQema(mobassist_pass).val();

    var onCredetChange = function() {
        var mobassist_qr_code_changed = $jQema("#mobassist_qr_code_changed");
        var qr = $jQema("#mobassist_qr_code");

        if(_old_login != $jQema(mobassist_login).val() || _old_pass != $jQema(mobassist_pass).val()) {


            if($jQema(qr).width() > 0 && $jQema(qr).attr("src") != "") {
                $jQema(mobassist_qr_code_changed).width($jQema(qr).width()).show("fast");
                qr.css('opacity', '0.5').show('fast');
            } else {
                $jQema(mobassist_qr_code_changed).hide("fast");
                qr.css('opacity', '1').show('fast');
            }
        } else {
            $jQema(mobassist_qr_code_changed).hide("fast");
            qr.css('opacity', '1').show('fast');
        }
    };

    mobassist_login.on("keyup", function () {
        onCredetChange();
    });

    $jQema(mobassist_pass).on("keyup", function () {
        onCredetChange();
    });

    $jQema('#submit-form').click(function() {
       if (mobassist_login.val().length == 0 || mobassist_pass.val().length == 0) {
           alert('Login and password cannot be empty.');
           return false;
       }
    });
});
